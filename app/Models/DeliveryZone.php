<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Zona propia de reparto, con su costo y su pedido mínimo. */
class DeliveryZone extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function acceptsSubtotal(int $subtotalCents): bool
    {
        return $subtotalCents >= $this->min_order_cents;
    }
}
