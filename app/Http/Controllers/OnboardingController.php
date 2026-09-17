<?php

namespace App\Http\Controllers;

use App\Enums\BusinessKind;
use App\Enums\BusinessRole;
use App\Enums\FulfillmentMethod;
use App\Enums\Offering;
use App\Enums\PaymentMethod;
use App\Enums\PlanCode;
use App\Enums\ServiceMode;
use App\Enums\SubscriptionState;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\ContentFlag;
use App\Models\Plan;
use App\Models\User;
use App\Models\Zone;
use App\Services\ContentScanner;
use App\Services\ScanResult;
use App\Support\ProhibitedItems;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Onboarding del negocio, por pasos que se pueden retomar.
 *
 * Cada paso se guarda en cuanto se envía: si la persona cierra la app a la
 * mitad, vuelve exactamente al paso donde se quedó. El orden importa porque
 * cada paso depende del anterior: lo que ofreces decide cómo entregas.
 */
class OnboardingController extends Controller
{
    public const STEPS = ['negocio', 'oferta', 'ubicacion', 'entrega', 'cobros', 'presentacion', 'reglas'];

    /** Usuarios que chocarían con rutas de la app. */
    private const RESERVED_SLUGS = [
        'admin', 'api', 'ajustes', 'dashboard', 'descubrir', 'inicio', 'login',
        'logout', 'modo', 'onboarding', 'p', 'register', 'settings', 'silvestre',
        'soporte', 'guias', 'pedidos', 'planes',
    ];

    public function __construct(private ContentScanner $scanner) {}

    public function show(Request $request): Response
    {
        $business = $request->user()->unfinishedBusiness();
        $reached = $business?->onboarding_step ?? 'negocio';

        // Se puede volver a un paso anterior, pero no saltarse uno pendiente.
        $step = $request->query('paso');
        if (! is_string($step) || ! in_array($step, self::STEPS, true) || $this->indexOf($step) > $this->indexOf($reached)) {
            $step = $reached;
        }

        return Inertia::render('onboarding/Business', [
            'step' => $step,
            'reached' => $reached,
            'steps' => self::STEPS,
            'business' => $business ? $this->present($business) : null,
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, string $step): RedirectResponse
    {
        abort_unless(in_array($step, self::STEPS, true), 404);

        $user = $request->user();
        $business = $user->unfinishedBusiness();

        if ($step !== 'negocio' && ! $business) {
            return redirect()->route('onboarding.show');
        }

        $business = DB::transaction(fn () => match ($step) {
            'negocio' => $this->saveIdentity($request, $user, $business),
            'oferta' => $this->saveOffering($request, $business),
            'ubicacion' => $this->saveLocation($request, $business),
            'entrega' => $this->saveFulfillment($request, $business),
            'cobros' => $this->savePayments($request, $business),
            'presentacion' => $this->savePresentation($request, $business),
            'reglas' => $this->acceptRules($request, $business),
        });

        if ($step === 'reglas') {
            return $this->finish($user, $business);
        }

        $next = self::STEPS[$this->indexOf($step) + 1];

        if ($this->indexOf($next) > $this->indexOf($business->onboarding_step ?? 'negocio')) {
            $business->forceFill(['onboarding_step' => $next])->save();
        }

        return redirect()->route('onboarding.show', ['paso' => $next]);
    }

    // -------------------------------------------------------------- 1. negocio

    private function saveIdentity(Request $request, User $user, ?Business $business): Business
    {
        $request->merge(['slug' => mb_strtolower(trim((string) $request->input('slug')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'slug' => [
                'required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9](?:[a-z0-9_.]*[a-z0-9])?$/',
                Rule::notIn(self::RESERVED_SLUGS),
                Rule::unique('businesses', 'slug')->ignore($business?->id),
            ],
            'kind' => ['required', Rule::enum(BusinessKind::class)],
            'legal_name' => ['nullable', 'required_if:kind,empresa', 'string', 'max:120'],
            'tax_id' => ['nullable', 'required_if:kind,empresa', 'regex:/^\d{1,12}-?[\dkK]$/'],
        ], [
            'slug.regex' => 'Usa solo minúsculas, números, punto o guion bajo, sin espacios.',
            'slug.not_in' => 'Ese usuario está reservado. Prueba con otro.',
            'slug.unique' => 'Ese usuario ya lo tiene otro negocio.',
            'legal_name.required_if' => 'Una empresa necesita su razón social.',
            'tax_id.required_if' => 'Una empresa necesita su NIT.',
            'tax_id.regex' => 'Escribe el NIT como aparece en tu RTU, por ejemplo 1234567-8.',
        ]);

        $this->guardProhibited('name', $data['name']);

        if ($data['kind'] !== BusinessKind::Empresa->value) {
            $data['legal_name'] = null;
            $data['tax_id'] = null;
        }

        if ($business) {
            $business->update($data);

            return $business;
        }

        $business = $user->ownedBusinesses()->create([
            ...$data,
            'city' => config('silvestre.city'),
        ]);

        $business->forceFill(['onboarding_step' => 'negocio'])->save();
        $business->members()->attach($user->id, [
            'role' => BusinessRole::Owner->value,
            'accepted_at' => now(),
        ]);

        // Todo negocio arranca en Gratis. La prueba de Pro empieza cuando el
        // perfil llega al 100 %, no ahora.
        $business->subscription()->create([
            'plan_id' => Plan::byCode(PlanCode::Free)->id,
            'state' => SubscriptionState::Active,
        ]);

        return $business;
    }

    // --------------------------------------------------------------- 2. oferta

    private function saveOffering(Request $request, Business $business): Business
    {
        $data = $request->validate([
            'offering' => ['required', Rule::enum(Offering::class)],
            'category_id' => ['required', 'integer'],
        ], [
            'offering.required' => 'Elige si vendes productos, servicios o ambos.',
            'category_id.required' => 'Elige la categoría que mejor te describe.',
        ]);

        $allowed = Category::query()
            ->for(Offering::from($data['offering']))
            ->whereKey($data['category_id'])
            ->exists();

        if (! $allowed) {
            throw ValidationException::withMessages([
                'category_id' => 'Esa categoría no corresponde a lo que ofreces. Elige otra.',
            ]);
        }

        $business->update($data);

        return $business;
    }

    // ------------------------------------------------------------ 3. ubicación

    private function saveLocation(Request $request, Business $business): Business
    {
        $whatsapp = preg_replace('/\D/', '', (string) $request->input('whatsapp'));
        if (strlen($whatsapp) === 11 && str_starts_with($whatsapp, '502')) {
            $whatsapp = substr($whatsapp, 3);
        }
        $request->merge(['whatsapp' => $whatsapp]);

        $data = $request->validate([
            'zone_id' => ['required', Rule::exists(Zone::class, 'id')],
            'address' => ['nullable', 'string', 'max:160'],
            'address_notes' => ['nullable', 'string', 'max:160'],
            'whatsapp' => ['required', 'digits:'.config('silvestre.phone.digits')],
            'hours' => ['required', 'array', 'min:1', 'max:7'],
            'hours.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'hours.*.opens_at' => ['required', 'date_format:H:i'],
            'hours.*.closes_at' => ['required', 'date_format:H:i', 'after:hours.*.opens_at'],
        ], [
            'zone_id.required' => 'Elige la zona donde está tu negocio.',
            'whatsapp.required' => 'Tu WhatsApp es como los clientes te escriben.',
            'whatsapp.digits' => 'El número debe tener 8 dígitos, sin el +502.',
            'hours.required' => 'Marca al menos un día en que atiendes.',
            'hours.min' => 'Marca al menos un día en que atiendes.',
            'hours.*.closes_at.after' => 'La hora de cierre debe ser después de la de apertura.',
        ]);

        $business->update(collect($data)->except('hours')->all());

        $business->hours()->delete();
        foreach ($data['hours'] as $row) {
            $business->hours()->create($row);
        }

        return $business;
    }

    // -------------------------------------------------------------- 4. entrega

    private function saveFulfillment(Request $request, Business $business): Business
    {
        $offering = $business->offering;
        $money = ['nullable', 'numeric', 'min:0', 'max:99999'];

        $rules = [
            'address' => ['nullable', 'string', 'max:160'],
            'delivery_zones' => ['nullable', 'array', 'max:40'],
            'delivery_zones.*.zone_id' => ['required', Rule::exists(Zone::class, 'id'), 'distinct'],
            'delivery_zones.*.fee' => $money,
            'delivery_zones.*.min_order' => $money,
        ];

        if ($offering->hasProducts()) {
            $rules['fulfillment'] = ['required', 'array', 'min:1'];
            $rules['fulfillment.*'] = [Rule::enum(FulfillmentMethod::class), 'distinct'];
            $rules['shipping_rate'] = $money;
            $rules['free_shipping_from'] = $money;
        }

        if ($offering->hasServices()) {
            $rules['service_modes'] = ['required', 'array', 'min:1'];
            $rules['service_modes.*'] = [Rule::enum(ServiceMode::class), 'distinct'];
            $rules['travel_fee'] = $money;
        }

        $data = $request->validate($rules, [
            'fulfillment.required' => 'Elige al menos una forma de entregar tus productos.',
            'service_modes.required' => 'Elige al menos un lugar donde das tus servicios.',
            'delivery_zones.*.zone_id.distinct' => 'Repetiste una zona.',
        ]);

        $fulfillment = collect($data['fulfillment'] ?? []);
        $modes = collect($data['service_modes'] ?? []);

        // Recoger en tienda o atender en tu local exigen una dirección.
        $needsAddress = $fulfillment->contains(FulfillmentMethod::Pickup->value)
            || $modes->contains(ServiceMode::AtBusiness->value);
        $address = trim((string) ($data['address'] ?? $business->address));

        if ($needsAddress && $address === '') {
            throw ValidationException::withMessages([
                'address' => 'Para que te visiten o recojan pedidos, necesitamos tu dirección.',
            ]);
        }

        // Llevar pedidos o ir a domicilio exige decir hasta dónde llegas.
        $needsZones = $fulfillment->contains(FulfillmentMethod::LocalDelivery->value)
            || $modes->contains(ServiceMode::AtCustomer->value);

        if ($needsZones && empty($data['delivery_zones'])) {
            throw ValidationException::withMessages([
                'delivery_zones' => 'Agrega al menos una zona a la que llegas.',
            ]);
        }

        if ($fulfillment->contains(FulfillmentMethod::Shipping->value) && ! isset($data['shipping_rate'])) {
            throw ValidationException::withMessages([
                'shipping_rate' => 'Indica cuánto cobras por envío (escribe 0 si es gratis).',
            ]);
        }

        if ($needsAddress) {
            $business->update(['address' => $address]);
        }

        $business->fulfillment()->delete();
        foreach ($fulfillment as $method) {
            $config = $method === FulfillmentMethod::Shipping->value ? [
                'shipping_rate_cents' => $this->cents($data['shipping_rate'] ?? 0),
                'free_from_cents' => isset($data['free_shipping_from']) ? $this->cents($data['free_shipping_from']) : null,
            ] : null;

            $business->fulfillment()->create(['method' => $method, 'config' => $config]);
        }

        $business->serviceModes()->delete();
        foreach ($modes as $mode) {
            $business->serviceModes()->create([
                'mode' => $mode,
                'travel_fee_cents' => $mode === ServiceMode::AtCustomer->value ? $this->cents($data['travel_fee'] ?? 0) : 0,
            ]);
        }

        $business->deliveryZones()->delete();
        if ($needsZones) {
            $names = Zone::whereIn('id', collect($data['delivery_zones'])->pluck('zone_id'))->pluck('name', 'id');

            foreach ($data['delivery_zones'] as $zone) {
                $business->deliveryZones()->create([
                    'zone_id' => $zone['zone_id'],
                    'name' => $names[$zone['zone_id']] ?? 'Zona',
                    'fee_cents' => $this->cents($zone['fee'] ?? 0),
                    'min_order_cents' => $this->cents($zone['min_order'] ?? 0),
                ]);
            }
        }

        return $business;
    }

    // --------------------------------------------------------------- 5. cobros

    private function savePayments(Request $request, Business $business): Business
    {
        $data = $request->validate([
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*.method' => ['required', Rule::enum(PaymentMethod::class), 'distinct'],
            'payment_methods.*.details' => ['nullable', 'array'],
            'payment_methods.*.details.bank' => ['nullable', Rule::in(config('silvestre.banks'))],
            'payment_methods.*.details.account_type' => ['nullable', Rule::in(config('silvestre.account_types'))],
            'payment_methods.*.details.account_number' => ['nullable', 'string', 'max:30'],
            'payment_methods.*.details.holder' => ['nullable', 'string', 'max:80'],
            'payment_methods.*.details.provider' => ['nullable', 'string', 'max:40'],
            'payment_methods.*.details.number' => ['nullable', 'string', 'max:20'],
            'payment_methods.*.details.url' => ['nullable', 'url:https', 'max:255'],
        ], [
            'payment_methods.required' => 'Elige al menos una forma en que tus clientes te pueden pagar.',
            'payment_methods.*.details.url.url' => 'El link debe empezar con https://',
        ]);

        // Cada método necesita sus propios datos para que el cliente pueda pagar.
        $errors = [];
        foreach ($data['payment_methods'] as $i => $row) {
            $method = PaymentMethod::from($row['method']);
            foreach ($method->requiredDetails() as $field) {
                if (blank($row['details'][$field] ?? null)) {
                    $errors["payment_methods.{$i}.details.{$field}"] = 'Este dato es necesario para que el cliente te pueda pagar.';
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $business->paymentMethods()->delete();
        foreach ($data['payment_methods'] as $row) {
            $method = PaymentMethod::from($row['method']);
            $business->paymentMethods()->create([
                'method' => $method,
                'details' => $method->requiredDetails()
                    ? collect($row['details'] ?? [])->only($method->requiredDetails())->all()
                    : null,
            ]);
        }

        return $business;
    }

    // --------------------------------------------------------- 6. presentación

    private function savePresentation(Request $request, Business $business): Business
    {
        $data = $request->validate([
            'intro' => ['required', 'string', 'min:10', 'max:160'],
            'about' => ['nullable', 'string', 'max:1500'],
        ], [
            'intro.required' => 'Escribe una frase que diga qué haces.',
            'intro.min' => 'Cuéntanos un poco más: al menos 10 caracteres.',
        ]);

        $result = $this->guardProhibited('intro', $business->name, $data['intro'], $data['about'] ?? '');

        $business->update($data);

        // Lo regulado o dudoso se publica, pero entra a revisión humana.
        if ($result->needsReview()) {
            ContentFlag::fromScan($business, $business, $result);
        }

        return $business;
    }

    // --------------------------------------------------------------- 7. reglas

    private function acceptRules(Request $request, Business $business): Business
    {
        $request->validate([
            'accept_policy' => ['accepted'],
        ], [
            'accept_policy.accepted' => 'Para abrir tu negocio necesitas aceptar las reglas de Silvestre.',
        ]);

        $business->forceFill([
            'policy_version' => ProhibitedItems::POLICY_VERSION,
            'policy_accepted_at' => now(),
            'policy_accepted_ip' => $request->ip(),
            'onboarding_step' => null,
            'onboarding_completed_at' => now(),
        ])->save();

        return $business;
    }

    private function finish(User $user, Business $business): RedirectResponse
    {
        $business->refreshActivation();
        $user->switchToBusiness($business);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "¡{$business->name} ya está en Silvestre! Sigue la guía para publicar tu primer producto.",
        ]);

        return redirect()->route('dashboard');
    }

    // -------------------------------------------------------------- utilidades

    /** Bloquea lo prohibido en el acto; devuelve el veredicto para lo demás. */
    private function guardProhibited(string $field, string ...$texts): ScanResult
    {
        $result = $this->scanner->scan(...$texts);

        if ($result->blocks()) {
            throw ValidationException::withMessages([$field => $result->message()]);
        }

        return $result;
    }

    private function cents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function indexOf(string $step): int
    {
        return (int) array_search($step, self::STEPS, true);
    }

    /** Lo que ya capturó, para rellenar el formulario al volver. */
    private function present(Business $business): array
    {
        $business->load(['hours', 'fulfillment', 'serviceModes', 'deliveryZones', 'paymentMethods', 'category']);
        $shipping = $business->fulfillment->firstWhere('method', FulfillmentMethod::Shipping);
        $atCustomer = $business->serviceModes->firstWhere('mode', ServiceMode::AtCustomer);

        return [
            'name' => $business->name,
            'slug' => $business->slug,
            'kind' => $business->kind->value,
            'legal_name' => $business->legal_name,
            'tax_id' => $business->tax_id,
            'offering' => $business->offering->value,
            'category_id' => $business->category_id,
            'category' => $business->category?->only(['name', 'requires_verification', 'required_document']),
            'zone_id' => $business->zone_id,
            'address' => $business->address,
            'address_notes' => $business->address_notes,
            'whatsapp' => $business->whatsapp,
            'hours' => $business->hours->map(fn (BusinessHour $h) => [
                'weekday' => $h->weekday,
                'opens_at' => substr((string) $h->opens_at, 0, 5),
                'closes_at' => substr((string) $h->closes_at, 0, 5),
            ])->values(),
            'fulfillment' => $business->fulfillment->map(fn ($f) => $f->method->value)->values(),
            'shipping_rate' => $shipping ? $shipping->shippingRateCents() / 100 : null,
            'free_shipping_from' => $shipping?->freeShippingFromCents() !== null ? $shipping->freeShippingFromCents() / 100 : null,
            'service_modes' => $business->serviceModes->map(fn ($m) => $m->mode->value)->values(),
            'travel_fee' => $atCustomer ? $atCustomer->travel_fee_cents / 100 : null,
            'delivery_zones' => $business->deliveryZones->map(fn ($z) => [
                'zone_id' => $z->zone_id,
                'fee' => $z->fee_cents / 100,
                'min_order' => $z->min_order_cents / 100,
            ])->values(),
            'payment_methods' => $business->paymentMethods->map(fn ($p) => [
                'method' => $p->method->value,
                'details' => $p->details ?? (object) [],
            ])->values(),
            'intro' => $business->intro,
            'about' => $business->about,
        ];
    }

    private function options(): array
    {
        $cases = fn (array $cases, ?callable $extra = null) => collect($cases)->map(fn ($c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'description' => $c->description(),
            ...($extra ? $extra($c) : []),
        ])->values();

        return [
            'kinds' => $cases(BusinessKind::cases()),
            'offerings' => $cases(Offering::cases()),
            'fulfillmentMethods' => $cases(FulfillmentMethod::cases()),
            'serviceModes' => $cases(ServiceMode::cases()),
            'paymentMethods' => $cases(PaymentMethod::cases(), fn (PaymentMethod $m) => [
                'details' => $m->requiredDetails(),
                'requiresProof' => $m->requiresProof(),
            ]),
            'categories' => Category::where('is_active', true)->orderBy('position')
                ->get(['id', 'name', 'offering', 'requires_verification', 'required_document'])
                ->map(fn (Category $c) => [...$c->toArray(), 'offering' => $c->offering->value]),
            'zones' => Zone::where('city', config('silvestre.city'))->orderBy('position')->get(['id', 'name']),
            'weekdays' => collect(BusinessHour::WEEKDAYS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            'banks' => config('silvestre.banks'),
            'accountTypes' => config('silvestre.account_types'),
            'wallets' => config('silvestre.wallets'),
            'currency' => config('silvestre.currency.symbol'),
            'phonePrefix' => config('silvestre.phone.prefix'),
            'city' => config('silvestre.city'),
            'trialDays' => (int) config('silvestre.trial_days'),
            'prohibited' => ProhibitedItems::prohibitedSummary(),
        ];
    }
}
