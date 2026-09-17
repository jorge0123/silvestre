<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un método con el que los clientes le pagan a este negocio. */
class BusinessPaymentMethod extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'is_active' => 'boolean',
            'details' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
