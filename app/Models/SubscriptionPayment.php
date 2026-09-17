<?php

namespace App\Models;

use App\Enums\SubscriptionPaymentState;
use App\Enums\SubscriptionPayMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pago de suscripción de un negocio a Silvestre. El único ingreso de la plataforma. */
class SubscriptionPayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'state' => SubscriptionPaymentState::class,
            'method' => SubscriptionPayMethod::class,
            'reviewed_at' => 'datetime',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
