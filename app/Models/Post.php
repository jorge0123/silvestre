<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /**
     * Tope del FEED, no del plan. Aunque un negocio Pro publique sin límite,
     * solo sus 2 publicaciones más recientes del día entran al feed de sus
     * seguidores; el resto vive en su perfil. Así nadie acapara, ni pagando.
     */
    public const MAX_PER_DAY_IN_FEED = 2;

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('position');
    }

    /** Producto o servicio enlazado; pinta la tarjeta dentro de la publicación. */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** Año-semana ISO; hace trivial contar el cupo semanal. */
    public static function weekKeyFor(?\DateTimeInterface $at = null): string
    {
        return \Illuminate\Support\Carbon::instance($at ?? now())->format('o-\WW');
    }
}
