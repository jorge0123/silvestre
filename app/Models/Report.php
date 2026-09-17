<?php

namespace App\Models;

use App\Enums\ReportReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'auto_hidden' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Report $report) {
            // El plazo se calcula del motivo: lo que puede lastimar a alguien
            // se atiende hoy; lo comercial puede esperar.
            $report->due_at ??= now()->addHours($report->reason->slaHours());
            $report->auto_hidden = $report->reason->autoHides();
        });
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /** La cola de moderación: lo más urgente y lo más vencido, primero. */
    public function scopeQueue(Builder $query): Builder
    {
        return $query->where('state', 'open')->orderBy('due_at');
    }

    public function isOverdue(): bool
    {
        return $this->state === 'open' && $this->due_at?->isPast();
    }
}
