<?php

namespace App\Enums;

/**
 * Estado de la suscripción de un negocio.
 *
 * Expired no borra ni oculta nada: el contenido ya publicado queda en solo
 * lectura. Quitarle a alguien el trabajo que ya subió no lo vuelve cliente.
 */
enum SubscriptionState: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'En prueba',
            self::Active => 'Activa',
            self::PastDue => 'Pago pendiente',
            self::Cancelled => 'Cancelada',
            self::Expired => 'Vencida',
        };
    }

    /** ¿Se le conceden los beneficios del plan de pago ahora mismo? */
    public function grantsPaidFeatures(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }

    /**
     * Degradación suave: al caer, lo existente se conserva pero se congela.
     * No se puede agregar más allá del tope del plan gratuito.
     */
    public function isReadOnlyFallback(): bool
    {
        return $this === self::Expired || $this === self::Cancelled;
    }
}
