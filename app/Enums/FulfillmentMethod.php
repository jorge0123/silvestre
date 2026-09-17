<?php

namespace App\Enums;

/**
 * Cómo llega un PRODUCTO al cliente.
 *
 * El negocio activa todas las que quiera; se guardan en business_fulfillment.
 * Cada una pide configuración distinta, y por eso el onboarding se bifurca aquí.
 */
enum FulfillmentMethod: string
{
    case Pickup = 'pickup';
    case LocalDelivery = 'local_delivery';
    case Shipping = 'shipping';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Recoger en tienda',
            self::LocalDelivery => 'Entrega propia',
            self::Shipping => 'Paquetería',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Pickup => 'El cliente pasa por su pedido a tu dirección.',
            self::LocalDelivery => 'Tú lo llevas dentro de las zonas que definas.',
            self::Shipping => 'Lo envías por transportista con número de guía.',
        };
    }

    /** Qué hay que pedirle al negocio para que este método funcione. */
    public function requires(): array
    {
        return match ($this) {
            self::Pickup => ['address', 'hours'],
            self::LocalDelivery => ['zones'],
            self::Shipping => ['shipping_rate'],
        };
    }
}
