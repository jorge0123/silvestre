<?php

namespace App\Models;

use App\Enums\BusinessKind;
use App\Enums\FulfillmentMethod;
use App\Enums\Offering;
use App\Enums\ServiceMode;
use App\Support\Platform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Offering $offering
 * @property BusinessKind $kind
 */
#[Fillable([
    'name', 'slug', 'kind', 'offering', 'intro', 'about', 'category_id',
    'zone_id', 'city', 'address', 'address_notes', 'whatsapp',
    'legal_name', 'tax_id', 'avatar_path', 'cover_path',
])]
class Business extends Model
{
    use SoftDeletes;

    /**
     * Constantes del puntaje bayesiano que ordena el directorio.
     * C = peso del promedio global, m = promedio global supuesto.
     * Con C=5, un 5.0 de dos reseñas no le gana a un 4.8 de ochenta.
     */
    public const BAYES_WEIGHT = 5;

    public const BAYES_PRIOR = 4.5;

    /** Reseñas mínimas antes de mostrar el promedio en público. */
    public const MIN_REVIEWS_TO_SHOW_AVG = 5;

    /** Porcentaje de activación por debajo del cual no aparece en búsqueda. */
    public const MIN_ACTIVATION_TO_PUBLISH = 60;

    /** Quien llega mientras "Todo libre" está encendido queda como fundador. */
    protected static function booted(): void
    {
        static::creating(function (Business $business) {
            if ($business->founder_at === null && Platform::freeMode()) {
                $business->founder_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'kind' => BusinessKind::class,
            'offering' => Offering::class,
            'activated_at' => 'datetime',
            'founder_at' => 'datetime',
            'published_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'policy_accepted_at' => 'datetime',
            'last_posted_at' => 'datetime',
            'is_verified' => 'boolean',
            'rating_bayes' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ---------------------------------------------------------------- relaciones

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_users')
            ->withPivot(['role', 'accepted_at'])->withTimestamps();
    }

    public function hours(): HasMany
    {
        return $this->hasMany(BusinessHour::class);
    }

    public function fulfillment(): HasMany
    {
        return $this->hasMany(BusinessFulfillment::class);
    }

    public function serviceModes(): HasMany
    {
        return $this->hasMany(BusinessServiceMode::class);
    }

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class);
    }

    /** Cómo le pagan los clientes. Silvestre no procesa ese dinero. */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(BusinessPaymentMethod::class);
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(StoryHighlight::class)->orderBy('position');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows')->withTimestamps();
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    // -------------------------------------------------------------------- scopes

    /** Solo negocios que pueden salir en búsqueda y recomendaciones. */
    public function scopeDiscoverable(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('activation_score', '>=', self::MIN_ACTIVATION_TO_PUBLISH);
    }

    /** Publicó al menos una vez en los últimos 7 días. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('last_posted_at', '>=', now()->subDays(7));
    }

    /**
     * Negocios con una suscripción vigente a un plan que incluye "Promocionado"
     * (prueba, pagado o con pago pendiente). Son los únicos que pueden salir
     * como publicidad en el Inicio. Qué planes lo incluyen se decide en el panel.
     */
    public function scopePro(Builder $query): Builder
    {
        // Se lee de las condiciones congeladas: si luego se le quita
        // "Promocionado" al plan, quien ya lo tenía lo conserva.
        return $query->whereHas('subscriptions', fn ($q) => $q
            ->whereIn('state', ['trial', 'active', 'past_due'])
            ->where(fn ($q) => $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('current_period_ends_at')->orWhere('current_period_ends_at', '>', now()))
            ->where(fn ($q) => $q
                ->where('terms->can_promote', true)
                ->orWhere(fn ($q) => $q->whereNull('terms')->whereHas('plan', fn ($p) => $p->where('can_promote', true)))));
    }

    /** ¿Está abierto en este momento, según su horario? */
    public function isOpenNow(): bool
    {
        return $this->hours->contains(fn (BusinessHour $h) => $h->coversNow());
    }

    /** Enlace de WhatsApp con un mensaje ya escrito. */
    public function whatsappUrl(?string $message = null): ?string
    {
        if (blank($this->whatsapp)) {
            return null;
        }

        $prefix = ltrim((string) config('silvestre.phone.prefix'), '+');
        $text = $message ?? "Hola, te encontré en Silvestre y quiero hacer un pedido.";

        return "https://wa.me/{$prefix}{$this->whatsapp}?text=".rawurlencode($text);
    }

    public function scopeRanked(Builder $query): Builder
    {
        return $query->orderByDesc('rating_bayes')->orderByDesc('followers_count');
    }

    // ------------------------------------------------------------- reputación

    /**
     * El promedio que se muestra al público. Se oculta hasta tener 5 reseñas:
     * un 5.0 con una sola reseña engaña más de lo que informa.
     */
    public function publicRating(): ?float
    {
        if ($this->rating_count < self::MIN_REVIEWS_TO_SHOW_AVG) {
            return null;
        }

        return round($this->rating_sum / max($this->rating_count, 1), 1);
    }

    /** Promedio bayesiano: el que ordena el directorio. */
    public function bayesianRating(): float
    {
        $c = self::BAYES_WEIGHT;
        $m = self::BAYES_PRIOR;

        return round((($c * $m) + $this->rating_sum) / ($c + $this->rating_count), 2);
    }

    /** Recalcula los contadores desnormalizados desde las reseñas reales. */
    public function recalculateRating(): void
    {
        $count = $this->reviews()->count();
        $sum = (int) $this->reviews()->sum('stars');

        $this->forceFill([
            'rating_count' => $count,
            'rating_sum' => $sum,
        ]);
        $this->rating_bayes = $this->bayesianRating();
        $this->save();
    }

    // ------------------------------------------------------------ capacidades

    public function sellsProducts(): bool
    {
        return $this->offering->hasProducts();
    }

    public function sellsServices(): bool
    {
        return $this->offering->hasServices();
    }

    /** @return array<int, FulfillmentMethod> */
    public function activeFulfillmentMethods(): array
    {
        // `method` ya viene convertido a enum por el cast del modelo.
        return $this->fulfillment->where('is_active', true)
            ->map(fn ($row) => $row->method instanceof FulfillmentMethod ? $row->method : FulfillmentMethod::from($row->method))
            ->values()->all();
    }

    /** @return array<int, ServiceMode> */
    public function activeServiceModes(): array
    {
        // `mode` ya viene convertido a enum por el cast del modelo.
        return $this->serviceModes->where('is_active', true)
            ->map(fn ($row) => $row->mode instanceof ServiceMode ? $row->mode : ServiceMode::from($row->mode))
            ->values()->all();
    }

    /** Atajo a los topes del plan vigente, con la degradación ya aplicada. */
    public function plan(): Plan
    {
        return $this->subscription?->effectivePlan() ?? Plan::free();
    }

    /** Llegó con "Todo libre": conserva todo sin límites para siempre. */
    public function isFounder(): bool
    {
        return $this->founder_at !== null;
    }

    /** ¿No se le aplica ningún tope? ("Todo libre" encendido o fundador) */
    public function isUnlimited(): bool
    {
        return Platform::freeMode() || $this->isFounder();
    }

    /**
     * Las condiciones que rigen hoy a este negocio, congeladas del día en que
     * entró a su plan. Si su plan de pago venció, vuelve a las condiciones
     * gratuitas que tenía al llegar (no a las del Gratis de hoy).
     *
     * @return array<string, int|bool|null>
     */
    public function terms(): array
    {
        return once(function () {
            $current = $this->subscription;

            if ($current?->isCurrent()) {
                return $current->currentTerms();
            }

            $firstFree = $this->subscriptions()
                ->whereHas('plan', fn ($p) => $p->where('code', \App\Enums\PlanCode::Free->value))
                ->oldest('id')
                ->first();

            return $firstFree?->currentTerms() ?? Plan::free()->terms();
        });
    }

    public function canCheckout(): bool
    {
        return $this->isUnlimited() || (bool) ($this->terms()['can_checkout'] ?? false);
    }

    /**
     * Un tope (post_quota_weekly, story_quota_daily, photo_limit,
     * product_limit, service_limit). null = sin límite.
     */
    public function limit(string $column): ?int
    {
        if ($this->isUnlimited()) {
            return null;
        }

        return $this->terms()[$column] ?? null;
    }

    /** Fotos o videos por publicación, con el tope técnico del servidor. */
    public function mediaPerPostLimit(): int
    {
        return min($this->limit('photo_limit') ?? Platform::MAX_MEDIA_PER_POST, Platform::MAX_MEDIA_PER_POST);
    }

    /**
     * Cupo semanal de publicaciones. null = ilimitado.
     * Ojo: esto es el límite del PLAN. El límite del FEED (2 por día por
     * negocio) es otra cosa y se aplica al construir el feed, no al publicar.
     */
    public function remainingWeeklyPosts(): ?int
    {
        $quota = $this->limit('post_quota_weekly');

        if ($quota === null) {
            return null;
        }

        $used = $this->posts()
            ->where('week_key', now()->format('o-\WW'))
            ->whereNotNull('published_at')
            ->count();

        return max(0, $quota - $used);
    }

    /** Historias que aún puede subir hoy. null = sin límite. */
    public function remainingStoriesToday(): ?int
    {
        $quota = $this->limit('story_quota_daily');

        if ($quota === null) {
            return null;
        }

        return max(0, $quota - $this->stories()->where('created_at', '>=', today())->count());
    }

    public function hasFinishedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    public function canPublishPost(): bool
    {
        $left = $this->remainingWeeklyPosts();

        return $left === null || $left > 0;
    }

    // ------------------------------------------------------------- activación

    /**
     * Un perfil a medio llenar es peor que no tenerlo. Esta lista es también
     * la checklist que ve el dueño durante el onboarding.
     *
     * @return array<string, bool>
     */
    public function activationChecklist(): array
    {
        $checks = [
            'Nombre y usuario' => filled($this->name) && filled($this->slug),
            'Presentación' => filled($this->intro),
            'Categoría' => $this->category_id !== null,
            'Foto de perfil' => filled($this->avatar_path),
            'Zona o dirección' => filled($this->address) || $this->zone_id !== null,
            'Horario' => $this->hours()->exists(),
            'Cómo te pagan' => $this->paymentMethods()->where('is_active', true)->exists(),
        ];

        if ($this->sellsProducts()) {
            $checks['Un producto'] = $this->products()->exists();
            $checks['Cómo entregas'] = $this->fulfillment()->where('is_active', true)->exists();
        }

        if ($this->sellsServices()) {
            $checks['Un servicio'] = $this->services()->exists();
            $checks['Dónde atiendes'] = $this->serviceModes()->where('is_active', true)->exists();
        }

        return $checks;
    }

    /** Recalcula el puntaje y, si llega a 100, arranca la prueba de 3 meses. */
    public function refreshActivation(): void
    {
        $checks = $this->activationChecklist();
        $score = (int) round((count(array_filter($checks)) / max(count($checks), 1)) * 100);

        $this->activation_score = $score;

        if ($score >= self::MIN_ACTIVATION_TO_PUBLISH && $this->published_at === null) {
            $this->published_at = now();
        }

        $justActivated = $score === 100 && $this->activated_at === null;

        if ($justActivated) {
            $this->activated_at = now();
        }

        $this->save();

        if ($justActivated) {
            $this->startTrial();
        }
    }

    /**
     * Arranca los días de prueba de Pro. Una sola vez en la vida del negocio:
     * si ya tuvo prueba o ya paga, no se repite.
     */
    public function startTrial(): void
    {
        $alreadyHadTrial = Subscription::where('business_id', $this->id)
            ->where(fn ($q) => $q->whereNotNull('trial_ends_at')
                ->orWhereHas('plan', fn ($p) => $p->where('code', '!=', \App\Enums\PlanCode::Free->value)))
            ->exists();

        if ($alreadyHadTrial) {
            return;
        }

        $this->subscription()->create([
            'plan_id' => Plan::byCode(\App\Enums\PlanCode::Pro)->id,
            'state' => \App\Enums\SubscriptionState::Trial,
            'trial_ends_at' => now()->addDays(Subscription::trialDays()),
        ]);
    }
}
