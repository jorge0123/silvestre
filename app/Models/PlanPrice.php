<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Precio de un plan para una duración: $3 por 1 mes, $6 por 3 meses. */
class PlanPrice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function monthlyCents(): int
    {
        return intdiv($this->price_cents, max($this->interval_months, 1));
    }

    public function formatted(): string
    {
        return config('silvestre.subscription_currency.symbol', '$')
            .rtrim(rtrim(number_format($this->price_cents / 100, 2), '0'), '.');
    }

    public function label(): string
    {
        return $this->interval_months === 1
            ? "{$this->formatted()} al mes"
            : "{$this->formatted()} por {$this->interval_months} meses";
    }
}
