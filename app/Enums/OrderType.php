<?php

namespace App\Enums;

/**
 * Pedidos y citas comparten tabla porque comparten cliente, negocio, pago,
 * estado de cuenta y reseña. Lo que cambia es la máquina de estados y, en el
 * caso de servicios, una fila complementaria en bookings.
 */
enum OrderType: string
{
    case Product = 'product';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Pedido',
            self::Service => 'Cita',
        };
    }

    /** El estado terminal feliz. Es el único que desbloquea la reseña. */
    public function completedState(): OrderState|BookingState
    {
        return match ($this) {
            self::Product => OrderState::Delivered,
            self::Service => BookingState::Completed,
        };
    }
}
