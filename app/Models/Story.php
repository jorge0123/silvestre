<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Una historia sale del FEED a las 24 h, pero no se borra: si está en un
 * destacado, sigue viéndose en el perfil para siempre. `expires_at` controla
 * visibilidad en el feed, nunca borrado.
 */
class Story extends Model
{
    protected $guarded = [];

    public const LIFETIME_HOURS = 24;

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function highlights(): BelongsToMany
    {
        return $this->belongsToMany(StoryHighlight::class, 'story_highlight_items')
            ->withPivot('position')->withTimestamps();
    }

    /** Las que aún salen en la fila de historias del feed. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function isLive(): bool
    {
        return $this->expires_at?->isFuture() ?? false;
    }

    /** Aunque haya caducado, sigue visible si alguien la destacó. */
    public function isVisibleOnProfile(): bool
    {
        return $this->isLive() || $this->highlights()->exists();
    }
}
