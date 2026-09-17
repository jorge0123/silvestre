<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro de todo lo que hace el equipo de Silvestre: moderación y cambios
 * de administración (planes, precios, permisos). Nunca se edita ni se borra.
 */
class ModerationAction extends Model
{
    protected $guarded = [];

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function log(User $by, Model $subject, string $action, string $reason, ?string $note = null): self
    {
        return self::create([
            'moderator_id' => $by->id,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'reason' => $reason,
            'note' => $note,
        ]);
    }
}
