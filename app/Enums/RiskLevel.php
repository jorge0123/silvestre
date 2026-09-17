<?php

namespace App\Enums;

/**
 * Qué tan grave es lo que se detectó en un contenido.
 *
 * La distinción importa: bloquear por palabra clave genera falsos positivos
 * ridículos ("pistola de silicón", "cuchillo de cocina", "armas de juguete").
 * Por eso solo lo inequívoco se bloquea; lo ambiguo se marca y lo revisa
 * una persona.
 */
enum RiskLevel: string
{
    case Prohibited = 'prohibited';
    case Restricted = 'restricted';
    case Sensitive = 'sensitive';
    case Allowed = 'allowed';

    public function label(): string
    {
        return match ($this) {
            self::Prohibited => 'Prohibido',
            self::Restricted => 'Requiere comprobante',
            self::Sensitive => 'Requiere revisión',
            self::Allowed => 'Permitido',
        };
    }

    /** ¿Se rechaza la publicación en el acto? */
    public function blocksPublication(): bool
    {
        return $this === self::Prohibited;
    }

    /** ¿Se publica pero entra a la cola de revisión humana? */
    public function needsReview(): bool
    {
        return $this === self::Restricted || $this === self::Sensitive;
    }

    /** Mensaje que ve el negocio. Explica qué pasó y qué hacer. */
    public function userMessage(): string
    {
        return match ($this) {
            self::Prohibited => 'Esto no se puede publicar en Silvestre. Está en la lista de artículos y servicios prohibidos.',
            self::Restricted => 'Esta categoría necesita un permiso o cédula. Súbelo en Verificación y lo revisamos en 48 horas.',
            self::Sensitive => 'Tu publicación quedó visible, pero una persona la va a revisar. Si todo está bien, no pasa nada más.',
            self::Allowed => 'Publicado.',
        };
    }
}
