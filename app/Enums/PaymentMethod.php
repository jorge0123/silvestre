<?php

namespace App\Enums;

/**
 * Cómo le paga el CLIENTE al NEGOCIO.
 *
 * Silvestre no procesa ninguno de estos pagos ni toca ese dinero: no hay
 * pasarela, no hay comisión y no hay responsabilidad financiera de la
 * plataforma. Cada negocio activa los que acepta y deja sus datos.
 *
 * Hay tres familias, y cada una mueve el pedido de forma distinta:
 *  - Contra entrega (efectivo o POS): el pedido avanza sin pago previo y se
 *    marca cobrado al entregar.
 *  - Con comprobante (transferencia o billetera): el cliente paga, sube la
 *    foto del comprobante y el negocio confirma en su banco.
 *  - Link propio: el negocio cobra con su propia herramienta y confirma.
 */
enum PaymentMethod: string
{
    case CashOnDelivery = 'cash_on_delivery';
    case CashOnPickup = 'cash_on_pickup';
    case CardOnDelivery = 'card_on_delivery';
    case BankTransfer = 'bank_transfer';
    case MobileWallet = 'mobile_wallet';
    case PaymentLink = 'payment_link';

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Efectivo contra entrega',
            self::CashOnPickup => 'Efectivo al recoger',
            self::CardOnDelivery => 'Tarjeta al recibir (POS del negocio)',
            self::BankTransfer => 'Transferencia o depósito',
            self::MobileWallet => 'Billetera móvil',
            self::PaymentLink => 'Link de pago propio',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'El cliente paga al recibir su pedido.',
            self::CashOnPickup => 'El cliente paga cuando pasa por su pedido.',
            self::CardOnDelivery => 'Cobras con tu propio datáfono al entregar.',
            self::BankTransfer => 'El cliente transfiere a tu cuenta y sube el comprobante.',
            self::MobileWallet => 'El cliente te paga por Tigo Money u otra billetera.',
            self::PaymentLink => 'Usas el link de cobro que ya tienes con tu banco o proveedor.',
        };
    }

    /** El cliente debe subir comprobante y el negocio confirmarlo. */
    public function requiresProof(): bool
    {
        return in_array($this, [self::BankTransfer, self::MobileWallet], true);
    }

    /** Se cobra en persona: el pedido no espera un pago previo. */
    public function isPaidInPerson(): bool
    {
        return in_array($this, [self::CashOnDelivery, self::CashOnPickup, self::CardOnDelivery], true);
    }

    /** Qué datos tiene que capturar el negocio para ofrecer este método. */
    public function requiredDetails(): array
    {
        return match ($this) {
            self::BankTransfer => ['bank', 'account_type', 'account_number', 'holder'],
            self::MobileWallet => ['provider', 'number'],
            self::PaymentLink => ['url'],
            default => [],
        };
    }

    /** Recoger en tienda no tiene sentido con efectivo contra entrega y viceversa. */
    public function fitsFulfillment(?FulfillmentMethod $method): bool
    {
        return match ($this) {
            self::CashOnPickup => $method === FulfillmentMethod::Pickup,
            self::CashOnDelivery, self::CardOnDelivery => $method !== FulfillmentMethod::Shipping,
            default => true,
        };
    }
}
