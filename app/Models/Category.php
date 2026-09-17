<?php

namespace App\Models;

use App\Enums\Offering;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['offering' => Offering::class, 'is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    /**
     * Qué categorías puede elegir un negocio según lo que vende.
     * "Plomería" no acepta a alguien que solo vende productos.
     */
    public function scopeFor(Builder $query, Offering $offering): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->where('offering', Offering::Ambos)
                ->orWhere('offering', $offering));
    }
}
