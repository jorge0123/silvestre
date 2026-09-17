<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una reseña por persona por negocio: la regla vive en el índice único de la
 * migración, no en un if de un controlador.
 */
class Review extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reply(): HasOne
    {
        return $this->hasOne(ReviewReply::class);
    }

    /** Solo lleva la insignia si nació de un pedido realmente entregado. */
    public function isVerifiedPurchase(): bool
    {
        return $this->order_id !== null;
    }

    /** Mantiene los contadores del negocio en sincronía sin un AVG en vivo. */
    protected static function booted(): void
    {
        static::saved(fn (Review $r) => $r->business->recalculateRating());
        static::deleted(fn (Review $r) => $r->business->recalculateRating());
    }
}
