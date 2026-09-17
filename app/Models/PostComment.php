<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Comentario en una publicación.
 *
 * Quién puede hacer qué:
 *  - Editar: solo quien lo escribió.
 *  - Borrar: quien lo escribió, o el negocio dueño de la publicación (para
 *    moderar su propio espacio). Borrar un comentario borra sus respuestas.
 *  - Reportar: cualquier otra persona con cuenta.
 */
class PostComment extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public const MAX_LENGTH = 1000;

    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'as_business_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    public function isEditableBy(?User $user): bool
    {
        return $user !== null && $user->id === $this->user_id;
    }

    public function isDeletableBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->id === $this->user_id
            || $user->is_admin
            || $user->canManage($this->post->business);
    }
}
