<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El libro de inventario. Append-only: nunca se edita ni se borra una fila.
 *
 * `qty` ya viene con signo. Una reserva es negativa pero no saca la pieza del
 * almacén: solo la aparta contra la disponibilidad.
 */
class StockMovement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->released_at === null
            && $this->expires_at->isPast();
    }
}
