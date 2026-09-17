<?php

namespace App\Models;

use App\Enums\FulfillmentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Qué métodos de entrega de productos tiene activos el negocio. */
class BusinessFulfillment extends Model
{
    protected $table = 'business_fulfillment';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'method' => FulfillmentMethod::class,
            'is_active' => 'boolean',
            'config' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** Tarifa base de paquetería, en centavos. */
    public function shippingRateCents(): int
    {
        return (int) ($this->config['shipping_rate_cents'] ?? 0);
    }

    /** Envío gratis a partir de este monto. null = nunca. */
    public function freeShippingFromCents(): ?int
    {
        return $this->config['free_from_cents'] ?? null;
    }
}
