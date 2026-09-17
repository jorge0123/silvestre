<?php

namespace App\Enums;

/**
 * Cómo se expresa un precio. Aplica a productos y a servicios.
 *
 * Quote existe porque obligar a un precio inventado (una mesa de postres,
 * una remodelación) es peor que admitir que hay que cotizar.
 */
enum PriceMode: string
{
    case Fixed = 'fixed';
    case From = 'from';
    case Hourly = 'hourly';
    case Quote = 'quote';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Precio fijo',
            self::From => 'Desde',
            self::Hourly => 'Por hora',
            self::Quote => 'Cotizar',
        };
    }

    /** Un precio a cotizar no puede pasar por el carrito: no hay monto que cobrar. */
    public function isPurchasable(): bool
    {
        return $this !== self::Quote;
    }

    public function format(?int $cents): string
    {
        if ($this === self::Quote || $cents === null) {
            return 'Cotizar';
        }

        // Moneda local del catálogo (quetzales en Guatemala).
        $amount = config('silvestre.currency.symbol', 'Q').number_format($cents / 100, 2);

        return match ($this) {
            self::Fixed => $amount,
            self::From => "desde {$amount}",
            self::Hourly => "{$amount} / hora",
            self::Quote => 'Cotizar',
        };
    }
}
