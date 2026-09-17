<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\BusinessRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string $email
 * @property string|null $phone
 * @property AccountType $account_type
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $created_at
 */
#[Fillable(['name', 'username', 'email', 'phone', 'password', 'account_type'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** Horas que una cuenta nueva debe esperar antes de poder calificar. */
    public const REVIEW_COOLDOWN_HOURS = 48;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'review_enabled_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'account_type' => AccountType::class,
            // No asignable en masa a propósito: nadie se vuelve admin desde un formulario.
            'is_admin' => 'boolean',
            'dismissed_guides' => 'array',
        ];
    }

    // ---------------------------------------------------------------- relaciones

    /** Negocios a los que pertenece, con su rol en el pivote. */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_users')
            ->withPivot(['role', 'accepted_at'])
            ->withTimestamps();
    }

    public function ownedBusinesses(): HasMany
    {
        return $this->hasMany(Business::class, 'owner_id');
    }

    public function follows(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'follows')->withTimestamps();
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** El negocio que está administrando ahora. null = modo personal. */
    public function activeBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'active_business_id');
    }

    // ------------------------------------------------------------------- modos

    /*
     * Una cuenta, dos modos. Nadie necesita dos cuentas:
     *  - Modo personal: compra, sigue negocios y deja reseñas.
     *  - Modo negocio: administra uno de sus negocios.
     *
     * `account_type` solo recuerda con qué intención se registró (para saber a
     * dónde mandarlo la primera vez). El modo real es `active_business_id`.
     */

    public function isInBusinessMode(): bool
    {
        return $this->active_business_id !== null;
    }

    /** Negocios que puede administrar: los suyos y los de su equipo aceptados. */
    public function manageableBusinesses()
    {
        return $this->businesses()->whereNotNull('business_users.accepted_at');
    }

    public function canManage(Business $business): bool
    {
        return $this->manageableBusinesses()->whereKey($business->id)->exists();
    }

    /** Cambia a modo negocio. Nunca a un negocio que no le pertenece. */
    public function switchToBusiness(Business $business): void
    {
        abort_unless($this->canManage($business), 403, 'No administras este negocio.');

        $this->forceFill(['active_business_id' => $business->id])->save();
    }

    public function switchToPersonal(): void
    {
        $this->forceFill(['active_business_id' => null])->save();
    }

    /** El negocio a medio configurar, si dejó el onboarding sin terminar. */
    public function unfinishedBusiness(): ?Business
    {
        return $this->ownedBusinesses()->whereNull('onboarding_completed_at')->latest()->first();
    }

    // ------------------------------------------------------------------ guías

    public function hasDismissedGuide(string $key): bool
    {
        return in_array($key, $this->dismissed_guides ?? [], true);
    }

    public function dismissGuide(string $key): void
    {
        $guides = $this->dismissed_guides ?? [];

        if (! in_array($key, $guides, true)) {
            $guides[] = $key;
            $this->forceFill(['dismissed_guides' => $guides])->save();
        }
    }

    // ------------------------------------------------------------------ dominio

    /** El negocio con el que está trabajando ahora, si está en modo negocio. */
    public function currentBusiness(): ?Business
    {
        return $this->active_business_id ? $this->activeBusiness : null;
    }

    public function roleIn(Business $business): ?BusinessRole
    {
        $pivot = $this->businesses->firstWhere('id', $business->id)?->pivot
            ?? $this->businesses()->where('businesses.id', $business->id)->first()?->pivot;

        return $pivot ? BusinessRole::from($pivot->role) : null;
    }

    public function canIn(Business $business, string $ability): bool
    {
        return $this->roleIn($business)?->can($ability) ?? false;
    }

    public function isBusinessAccount(): bool
    {
        return $this->account_type === AccountType::Business;
    }

    /**
     * Antifraude: una cuenta recién creada no puede calificar, y hay que estar
     * verificado. Sin esto, cualquiera abre diez cuentas y fabrica un 5.0.
     */
    public function canLeaveReviews(): bool
    {
        if ($this->email_verified_at === null && $this->phone_verified_at === null) {
            return false;
        }

        return $this->created_at?->addHours(self::REVIEW_COOLDOWN_HOURS)->isPast() ?? false;
    }

    public function isFollowing(Business $business): bool
    {
        return $this->follows()->whereKey($business->id)->exists();
    }
}
