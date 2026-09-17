<?php

namespace App\Models;

use App\Enums\SubscriptionState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $guarded = [];

    /** Duración de la prueba, en días, contada desde la activación del perfil. */
    public static function trialDays(): int
    {
        return (int) config('silvestre.trial_days', 90);
    }

    /**
     * Al nacer, la suscripción copia las condiciones del plan y el precio.
     * Así, si luego el plan cambia (menos publicaciones, otro precio), los
     * negocios que ya estaban conservan lo que aceptaron al entrar.
     */
    protected static function booted(): void
    {
        static::creating(function (Subscription $sub) {
            if ($sub->terms !== null) {
                return;
            }

            $plan = Plan::find($sub->plan_id);
            $price = $sub->plan_price_id ? PlanPrice::find($sub->plan_price_id) : null;

            $sub->terms = [
                ...($plan?->terms() ?? []),
                'price_cents' => $price?->price_cents,
                'interval_months' => $price?->interval_months,
            ];
        });
    }

    protected function casts(): array
    {
        return [
            'terms' => 'array',
            'state' => SubscriptionState::class,
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'renews_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * Degradación suave: si la suscripción venció, el negocio opera con los
     * topes del plan gratuito, pero NADA de lo ya publicado se borra ni se
     * oculta. Solo deja de poder agregar más allá del tope.
     */
    public function effectivePlan(): Plan
    {
        return $this->isCurrent()
            ? $this->plan
            : Plan::free();
    }

    /** ¿Da sus beneficios hoy? (estado vigente y sin plazo vencido) */
    public function isCurrent(): bool
    {
        return $this->state->grantsPaidFeatures()
            && ($this->trial_ends_at === null || $this->trial_ends_at->isFuture())
            && ($this->current_period_ends_at === null || $this->current_period_ends_at->isFuture());
    }

    /**
     * Las condiciones congeladas de esta suscripción. Si falta alguna clave
     * (una función que se agregó después), se completa con el plan actual.
     *
     * @return array<string, int|bool|null>
     */
    public function currentTerms(): array
    {
        return [...$this->plan->terms(), ...($this->terms ?? [])];
    }

    public function isOnTrial(): bool
    {
        return $this->state === SubscriptionState::Trial
            && $this->trial_ends_at?->isFuture();
    }

    public function trialDaysLeft(): int
    {
        if (! $this->trial_ends_at) {
            return 0;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->trial_ends_at->startOfDay(), false));
    }

    /** Marca como vencida una prueba cuyo plazo ya pasó. */
    public function expireIfDue(): void
    {
        if ($this->state === SubscriptionState::Trial && $this->trial_ends_at?->isPast()) {
            $this->update([
                'state' => SubscriptionState::Expired,
                'ended_at' => now(),
            ]);
        }
    }
}
