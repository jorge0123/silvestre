<?php

namespace App\Models;

use App\Enums\RiskLevel;
use App\Services\ScanResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentFlag extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['risk' => RiskLevel::class, 'reviewed_at' => 'datetime'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function flaggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('state', 'open');
    }

    /** Crea el registro a partir del veredicto del escáner. */
    public static function fromScan(Model $subject, Business $business, ScanResult $result): ?self
    {
        if ($result->isAllowed()) {
            return null;
        }

        return self::create([
            'business_id' => $business->id,
            'flaggable_type' => $subject::class,
            'flaggable_id' => $subject->getKey(),
            ...$result->toArray(),
        ]);
    }
}
