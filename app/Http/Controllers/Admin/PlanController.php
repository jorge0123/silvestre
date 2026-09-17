<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationAction;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\SubscriptionPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Crear y editar planes: nombre, topes, capacidades y precios.
 *
 * Reglas para no romper nada:
 *  - Gratis y Pro no se pueden borrar ni cambiar de código: la app los usa.
 *  - Gratis no se puede desactivar: todo negocio nuevo arranca ahí.
 *  - Un plan con suscripciones no se borra; se desactiva.
 *  - Un precio con pagos no se borra; se desactiva, para no perder historial.
 *
 * Y la regla comercial: cada suscripción guarda las condiciones del día en
 * que entró. Editar un plan solo afecta a quien entre después, salvo que se
 * marque "aplicar también a los actuales" (útil para MEJORAR un plan). El
 * precio de los actuales nunca cambia desde aquí.
 */
class PlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/plans/Index', [
            'plans' => Plan::with('prices')->withCount([
                'subscriptions as active_subscriptions' => fn ($q) => $q->whereIn('state', ['trial', 'active', 'past_due']),
            ])->orderBy('position')->get()->map(fn (Plan $p) => $this->present($p)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/plans/Form', ['plan' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $plan = DB::transaction(function () use ($data) {
            $plan = Plan::create([...$this->attributes($data), 'code' => $data['code'], 'price_cents' => 0]);
            $this->syncPrices($plan, $data['prices'] ?? []);

            return $plan;
        });

        ModerationAction::log($request->user(), $plan, 'plan.create', "Creó el plan {$plan->name}");
        Inertia::flash('toast', ['type' => 'success', 'message' => "Plan {$plan->name} creado."]);

        return redirect()->route('admin.plans.index');
    }

    public function edit(Plan $plan): Response
    {
        $plan->load('prices')->loadCount([
            'subscriptions as active_subscriptions' => fn ($q) => $q->whereIn('state', ['trial', 'active', 'past_due']),
        ]);

        return Inertia::render('admin/plans/Form', ['plan' => $this->present($plan)]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $this->validated($request, $plan);

        if ($plan->hasCode('free') && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => 'El plan Gratis no se puede desactivar: todo negocio nuevo empieza ahí.']);
        }

        $before = $plan->only([...Plan::LIMITS, ...Plan::CAPABILITIES, 'name', 'is_active']);
        $pricesBefore = $plan->prices->map(fn (PlanPrice $p) => $p->label())->join(', ');

        $applied = DB::transaction(function () use ($plan, $data) {
            $plan->update($this->attributes($data));
            $this->syncPrices($plan, $data['prices'] ?? []);

            if (! ($data['apply_to_current'] ?? false)) {
                return 0;
            }

            // Solo topes y funciones; el precio que cada quien aceptó se respeta.
            $subs = $plan->subscriptions()->whereIn('state', ['trial', 'active', 'past_due'])->get();
            foreach ($subs as $sub) {
                $sub->update(['terms' => [...($sub->terms ?? []), ...$plan->refresh()->terms()]]);
            }

            return $subs->count();
        });

        $plan->refresh()->load('prices');
        $changes = collect($plan->only(array_keys($before)))
            ->filter(fn ($value, $key) => $before[$key] !== $value)
            ->map(fn ($value, $key) => "{$key}: ".var_export($before[$key], true).' → '.var_export($value, true))
            ->values();
        $pricesAfter = $plan->prices->map(fn (PlanPrice $p) => $p->label())->join(', ');
        if ($pricesBefore !== $pricesAfter) {
            $changes->push("precios: {$pricesBefore} → {$pricesAfter}");
        }

        if ($applied > 0) {
            $changes->push("aplicado también a {$applied} negocios actuales");
        }

        ModerationAction::log($request->user(), $plan, 'plan.update', "Editó el plan {$plan->name}", $changes->join('; ') ?: null);
        Inertia::flash('toast', ['type' => 'success', 'message' => $applied > 0
            ? "Plan {$plan->name} actualizado, también para {$applied} ".($applied === 1 ? 'negocio actual' : 'negocios actuales').'.'
            : "Plan {$plan->name} actualizado. Aplica a quienes entren desde ahora."]);

        return redirect()->route('admin.plans.index');
    }

    public function destroy(Request $request, Plan $plan): RedirectResponse
    {
        if ($plan->isProtected()) {
            throw ValidationException::withMessages(['plan' => "{$plan->name} es un plan base y no se puede borrar."]);
        }

        if ($plan->subscriptions()->exists()) {
            throw ValidationException::withMessages(['plan' => "{$plan->name} tiene negocios suscritos. Desactívalo en lugar de borrarlo."]);
        }

        $name = $plan->name;
        $plan->delete();

        ModerationAction::log($request->user(), $request->user(), 'plan.delete', "Borró el plan {$name}");
        Inertia::flash('toast', ['type' => 'success', 'message' => "Plan {$name} borrado."]);

        return redirect()->route('admin.plans.index');
    }

    // -------------------------------------------------------------- utilidades

    private function validated(Request $request, ?Plan $plan): array
    {
        $limit = ['nullable', 'integer', 'min:0', 'max:65000'];

        return $request->validate([
            'code' => $plan ? ['prohibited'] : ['required', 'string', 'regex:/^[a-z0-9_-]{2,30}$/', Rule::unique('plans', 'code')],
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'position' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
            'apply_to_current' => ['sometimes', 'boolean'],
            ...collect(Plan::LIMITS)->mapWithKeys(fn ($l) => [$l => $limit])->all(),
            ...collect(Plan::CAPABILITIES)->mapWithKeys(fn ($c) => [$c => ['required', 'boolean']])->all(),
            'prices' => ['array', 'max:6'],
            'prices.*.id' => ['nullable', 'integer', Rule::exists('plan_prices', 'id')->where('plan_id', $plan?->id ?? 0)],
            'prices.*.interval_months' => ['required', 'integer', 'min:1', 'max:36', 'distinct'],
            'prices.*.price' => ['required', 'numeric', 'min:0', 'max:9999'],
            'prices.*.is_active' => ['required', 'boolean'],
        ], [
            'code.regex' => 'Usa minúsculas, números, guion o guion bajo (2 a 30).',
            'code.unique' => 'Ya existe un plan con ese código.',
            'code.prohibited' => 'El código de un plan no se puede cambiar.',
            'prices.*.interval_months.distinct' => 'Hay dos precios con la misma duración.',
        ]);
    }

    private function attributes(array $data): array
    {
        return collect($data)->only(['name', 'description', 'position', 'is_active', ...Plan::LIMITS, ...Plan::CAPABILITIES])->all();
    }

    private function syncPrices(Plan $plan, array $rows): void
    {
        $keep = [];

        foreach ($rows as $row) {
            $price = $plan->prices()->updateOrCreate(
                ['id' => $row['id'] ?? null],
                [
                    'interval_months' => $row['interval_months'],
                    'price_cents' => (int) round($row['price'] * 100),
                    'currency' => 'USD',
                    'is_active' => $row['is_active'],
                ],
            );
            $keep[] = $price->id;
        }

        foreach ($plan->prices()->whereNotIn('id', $keep)->get() as $removed) {
            // Con pagos registrados solo se desactiva: el historial debe cuadrar.
            if (SubscriptionPayment::where('plan_price_id', $removed->id)->exists()) {
                $removed->update(['is_active' => false]);
            } else {
                $removed->delete();
            }
        }
    }

    private function present(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'position' => $plan->position,
            'is_active' => $plan->is_active,
            'protected' => $plan->isProtected(),
            'activeSubscriptions' => $plan->active_subscriptions ?? null,
            ...collect(Plan::LIMITS)->mapWithKeys(fn ($l) => [$l => $plan->{$l}])->all(),
            ...collect(Plan::CAPABILITIES)->mapWithKeys(fn ($c) => [$c => (bool) $plan->{$c}])->all(),
            'prices' => $plan->prices->map(fn (PlanPrice $p) => [
                'id' => $p->id,
                'interval_months' => $p->interval_months,
                'price' => $p->price_cents / 100,
                'is_active' => $p->is_active,
                'label' => $p->label(),
            ])->values(),
        ];
    }
}
