<?php

namespace App\Enums;

/**
 * Cómo le paga un NEGOCIO su suscripción a Silvestre.
 *
 * Por ahora solo transferencia a la cuenta de Banco Industrial, con
 * comprobante y aprobación manual: cero comisiones. Cuando haya volumen se
 * agrega aquí un cobro automático con tarjeta.
 */
enum SubscriptionPayMethod: string
{
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Transferencia a Banco Industrial',
        };
    }
}
