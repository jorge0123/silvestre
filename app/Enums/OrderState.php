<?php

namespace App\Enums;

/**
 * Máquina de estados de un pedido de PRODUCTOS.
 *
 * Reglas duras:
 *  - Paid descuenta inventario. PendingPayment y PaymentSubmitted solo
 *    reservan, con caducidad.
 *  - Silvestre no procesa pagos: PaymentSubmitted significa que el cliente
 *    subió su comprobante y el NEGOCIO tiene que verificarlo en su banco.
 *  - Delivered es lo único que habilita dejar reseña.
 *  - Las transiciones válidas viven aquí, no repartidas por los controladores.
 */
enum OrderState: string
{
    case PendingPayment = 'pending_payment';
    case PaymentSubmitted = 'payment_submitted';
    case Paid = 'paid';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pendiente de pago',
            self::PaymentSubmitted => 'Pago por confirmar',
            self::Paid => 'Pagado',
            self::Accepted => 'Aceptado',
            self::Preparing => 'En preparación',
            self::Ready => 'Listo',
            self::InTransit => 'En camino',
            self::Delivered => 'Entregado',
            self::Cancelled => 'Cancelado',
            self::Refunded => 'Reembolsado',
            self::Expired => 'Expirado',
        };
    }

    /** Para pintar el chip en la interfaz. */
    public function tone(): string
    {
        return match ($this) {
            self::PendingPayment, self::PaymentSubmitted => 'wait',
            self::Paid, self::Accepted, self::Preparing, self::Ready, self::InTransit => 'move',
            self::Delivered => 'done',
            self::Cancelled, self::Refunded, self::Expired => 'stop',
        };
    }

    /** @return array<int, self> */
    public function allowedNext(): array
    {
        return match ($this) {
            // Contra entrega: el negocio acepta sin pago previo.
            self::PendingPayment => [self::PaymentSubmitted, self::Paid, self::Accepted, self::Cancelled, self::Expired],
            // Si el comprobante no cuadra, vuelve a pendiente para que el
            // cliente suba otro; no se cancela de golpe.
            self::PaymentSubmitted => [self::Paid, self::PendingPayment, self::Cancelled],
            self::Paid => [self::Accepted, self::Cancelled, self::Refunded],
            self::Accepted => [self::Preparing, self::Cancelled, self::Refunded],
            self::Preparing => [self::Ready, self::Cancelled, self::Refunded],
            self::Ready => [self::InTransit, self::Delivered, self::Refunded],
            self::InTransit => [self::Delivered, self::Refunded],
            self::Delivered => [self::Refunded],
            self::Cancelled, self::Refunded, self::Expired => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }

    /** Estados en los que el inventario ya salió de verdad. */
    public function hasConsumedStock(): bool
    {
        return in_array($this, [
            self::Paid, self::Accepted, self::Preparing,
            self::Ready, self::InTransit, self::Delivered,
        ], true);
    }

    public function unlocksReview(): bool
    {
        return $this === self::Delivered;
    }
}
