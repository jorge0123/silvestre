<?php

namespace App\Models;

use App\Enums\BookingState;
use App\Enums\ServiceMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Fila complementaria 1:1 con una orden de tipo servicio.
 * Aquí vive lo que un pedido de productos no necesita: agenda y cotización.
 */
class Booking extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'state' => BookingState::class,
            'mode' => ServiceMode::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function endsAt(): ?Carbon
    {
        return $this->scheduled_at?->copy()->addMinutes($this->duration_min);
    }

    /** Una cita a domicilio necesita dirección; una en línea, no. */
    public function requiresAddress(): bool
    {
        return $this->mode === ServiceMode::AtCustomer;
    }
}
