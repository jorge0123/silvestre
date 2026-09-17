<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Historias destacadas: colecciones permanentes en el perfil.
 *
 * Es lo que convierte historias desechables en presentación del negocio:
 * "Cómo trabajo", "Entregas", "Pedidos especiales".
 */
class StoryHighlight extends Model
{
    protected $guarded = [];

    /** Cuántos destacados puede tener un perfil, para que no se vuelva ruido. */
    public const MAX_PER_BUSINESS = 8;

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'story_highlight_items')
            ->withPivot('position')->withTimestamps()
            ->orderBy('story_highlight_items.position');
    }

    /** Si no se eligió portada, se usa la primera historia de la colección. */
    public function coverPath(): ?string
    {
        return $this->cover_path ?? $this->stories()->first()?->media_path;
    }
}
