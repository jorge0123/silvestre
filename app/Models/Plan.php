<?php

namespace App\Models;

use App\Enums\PlanCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un plan. Los topes viven aquí como datos para poder cambiarlos desde el
 * panel sin desplegar. null en cualquier límite significa sin límite.
 *
 * `code` es texto libre: además de los planes base (free, pro), el equipo
 * puede crear los que quiera desde el panel.
 */
class Plan extends Model
{
    protected $guarded = [];

    /** Planes base que la app necesita para funcionar: no se pueden borrar. */
    public const PROTECTED_CODES = ['free', 'pro'];

    /** Topes numéricos del plan. null = sin límite. */
    public const LIMITS = ['post_quota_weekly', 'story_quota_daily', 'photo_limit', 'product_limit', 'service_limit'];

    /** Funciones que el plan enciende o apaga. */
    public const CAPABILITIES = ['can_checkout', 'can_reserve_stock', 'can_use_team', 'can_invoice', 'can_promote'];

    protected function casts(): array
    {
        return [
            'can_checkout' => 'boolean',
            'can_reserve_stock' => 'boolean',
            'can_use_team' => 'boolean',
            'can_invoice' => 'boolean',
            'can_promote' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class)->orderBy('interval_months');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public static function free(): self
    {
        return once(fn () => self::where('code', PlanCode::Free->value)->firstOrFail());
    }

    public static function byCode(PlanCode|string $code): self
    {
        return self::where('code', $code instanceof PlanCode ? $code->value : $code)->firstOrFail();
    }

    public function hasCode(PlanCode|string $code): bool
    {
        return $this->code === ($code instanceof PlanCode ? $code->value : $code);
    }

    public function isProtected(): bool
    {
        return in_array($this->code, self::PROTECTED_CODES, true);
    }

    /** "$4 al mes o $8 por 3 meses", leído de plan_prices. */
    public static function proPricingLabel(): string
    {
        $prices = once(fn () => self::where('code', PlanCode::Pro->value)->first()?->prices()->where('is_active', true)->get());

        return $prices && $prices->isNotEmpty()
            ? $prices->map(fn (PlanPrice $p) => $p->label())->join(', ', ' o ')
            : '';
    }

    /**
     * Las condiciones de hoy (topes y funciones), tal como se copian en cada
     * suscripción nueva. Editar el plan después no toca esas copias.
     *
     * @return array<string, int|bool|null>
     */
    public function terms(): array
    {
        return [
            ...collect(self::LIMITS)->mapWithKeys(fn ($l) => [$l => $this->{$l} === null ? null : (int) $this->{$l}])->all(),
            ...collect(self::CAPABILITIES)->mapWithKeys(fn ($c) => [$c => (bool) $this->{$c}])->all(),
        ];
    }

    public function isUnlimitedPosts(): bool
    {
        return $this->post_quota_weekly === null;
    }
}
