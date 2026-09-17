<?php

namespace App\Enums;

/**
 * Estado del pago de una suscripción (negocio → Silvestre).
 *
 * Mientras el cobro sea manual, cada pago lo aprueba una persona del equipo
 * de Silvestre después de verlo reflejado en la cuenta.
 */
enum SubscriptionPaymentState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En revisión',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
        };
    }
}
