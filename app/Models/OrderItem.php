<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El nombre y el precio quedan congelados aquí: un pedido debe poder leerse
 * aunque el producto se renombre, cambie de precio o se borre del catálogo.
 */
class OrderItem extends Model
{
    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function lineTotalCents(): int
    {
        return $this->qty * $this->unit_price_cents;
    }

    public function lineCostCents(): int
    {
        return $this->qty * (int) $this->unit_cost_cents;
    }
}
