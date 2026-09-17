<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** La línea de tiempo del rastreo. Append-only: nunca se edita ni se borra. */
class OrderEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['happened_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
