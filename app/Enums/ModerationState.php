<?php

namespace App\Enums;

/**
 * Estado de moderación de un negocio o de una pieza de contenido.
 *
 * Limited existe para no tener que elegir entre "no pasa nada" y "adiós":
 * el negocio sigue operando con lo que ya tiene, pero no puede publicar
 * ni aparecer en descubrimiento mientras se resuelve el caso.
 */
enum ModerationState: string
{
    case Ok = 'ok';
    case Flagged = 'flagged';
    case UnderReview = 'under_review';
    case Limited = 'limited';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Sin observaciones',
            self::Flagged => 'Marcado',
            self::UnderReview => 'En revisión',
            self::Limited => 'Limitado',
            self::Suspended => 'Suspendido',
        };
    }

    /** ¿Puede publicar contenido nuevo? */
    public function canPublish(): bool
    {
        return in_array($this, [self::Ok, self::Flagged], true);
    }

    /** ¿Sale en búsqueda y recomendaciones? */
    public function isDiscoverable(): bool
    {
        return in_array($this, [self::Ok, self::Flagged, self::UnderReview], true);
    }

    /** ¿Puede recibir pedidos? */
    public function canSell(): bool
    {
        return $this !== self::Suspended;
    }
}
