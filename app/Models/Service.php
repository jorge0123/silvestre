<?php

namespace App\Models;

use App\Enums\PriceMode;
use App\Enums\ServiceMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Un servicio no tiene inventario ni variantes: tiene duración, capacidad,
 * anticipación mínima y modalidad. Por eso nunca pasa por el carrito.
 */
#[Fillable([
    'name', 'slug', 'description', 'price_mode', 'price_cents',
    'duration_min', 'buffer_min', 'capacity_per_day', 'min_notice_hours',
    'modes', 'is_active', 'position',
])]
class Service extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'price_mode' => PriceMode::class,
            'modes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ServiceMedia::class)->orderBy('position');
    }

    /** @return array<int, ServiceMode> */
    public function availableModes(): array
    {
        $own = collect($this->modes ?? [])->map(fn ($m) => $m instanceof ServiceMode ? $m : ServiceMode::from($m));

        // Un servicio no puede ofrecer una modalidad que el negocio no tiene
        // activa: si el negocio no va a domicilio, este servicio tampoco.
        $allowed = collect($this->business->activeServiceModes());

        if ($own->isEmpty()) {
            return $allowed->all();
        }

        // Se compara por valor: los enums no se pueden cruzar como texto.
        $allowedValues = $allowed->map(fn (ServiceMode $m) => $m->value)->all();

        return $own->filter(fn (ServiceMode $m) => in_array($m->value, $allowedValues, true))->values()->all();
    }

    public function formattedPrice(): string
    {
        return $this->price_mode->format($this->price_cents);
    }

    /** Un servicio a cotizar se solicita, no se paga por adelantado. */
    public function requiresQuote(): bool
    {
        return $this->price_mode === PriceMode::Quote;
    }

    /** La primera hora a la que se puede agendar, respetando la anticipación. */
    public function earliestSlot(): Carbon
    {
        return now()->addHours($this->min_notice_hours);
    }

    /** Citas ya comprometidas para un día, contra la capacidad diaria. */
    public function remainingCapacityOn(Carbon $day): int
    {
        if ($this->capacity_per_day === null) {
            return PHP_INT_MAX;
        }

        $taken = $this->bookings()
            ->whereDate('scheduled_at', $day)
            ->whereIn('state', ['requested', 'quoted', 'confirmed', 'in_progress'])
            ->count();

        return max(0, $this->capacity_per_day - $taken);
    }

    public function isBookableOn(Carbon $day): bool
    {
        return $this->is_active
            && $day->greaterThanOrEqualTo($this->earliestSlot()->startOfDay())
            && $this->remainingCapacityOn($day) > 0;
    }

    /** Cuánto bloquea realmente la agenda, incluyendo el margen entre citas. */
    public function blockMinutes(): int
    {
        return $this->duration_min + $this->buffer_min;
    }
}
