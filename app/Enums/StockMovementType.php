<?php

namespace App\Enums;

/**
 * El stock nunca se edita: se mueve.
 *
 * La existencia actual de una variante es la suma de su libro de movimientos.
 * Así siempre se puede contestar "¿por qué dice 2 si compré 24?" y ningún
 * empleado puede alterar el inventario sin dejar rastro.
 */
enum StockMovementType: string
{
    case Entry = 'entry';
    case Sale = 'sale';
    case Reservation = 'reservation';
    case Release = 'release';
    case Adjustment = 'adjustment';
    case Waste = 'waste';
    case ReturnIn = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Entrada',
            self::Sale => 'Venta',
            self::Reservation => 'Reserva',
            self::Release => 'Liberación',
            self::Adjustment => 'Ajuste',
            self::Waste => 'Merma',
            self::ReturnIn => 'Devolución',
        };
    }

    /** Signo con el que el movimiento entra al libro. Adjustment puede ser ambos. */
    public function sign(): int
    {
        return match ($this) {
            self::Entry, self::Release, self::ReturnIn => 1,
            self::Sale, self::Reservation, self::Waste => -1,
            self::Adjustment => 0,
        };
    }

    /**
     * Una reserva aparta la pieza sin sacarla del almacén. Cuenta contra la
     * disponibilidad para vender, pero no contra la existencia física.
     */
    public function affectsAvailableOnly(): bool
    {
        return $this === self::Reservation || $this === self::Release;
    }

    public function requiresReason(): bool
    {
        return $this === self::Adjustment || $this === self::Waste;
    }
}
