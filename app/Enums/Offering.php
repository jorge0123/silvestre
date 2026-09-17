<?php

namespace App\Enums;

/**
 * Qué vende el negocio. Es la bifurcación más importante del onboarding.
 *
 * Productos viven de inventario y se compran por carrito.
 * Servicios viven de agenda y capacidad, y se solicitan como cita.
 * Nunca se mezclan en un mismo pedido, aunque el negocio ofrezca ambos.
 */
enum Offering: string
{
    case Productos = 'productos';
    case Servicios = 'servicios';
    case Ambos = 'ambos';

    public function label(): string
    {
        return match ($this) {
            self::Productos => 'Productos',
            self::Servicios => 'Servicios',
            self::Ambos => 'Productos y servicios',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Productos => 'Cosas que empacas y entregas. Llevan inventario y carrito.',
            self::Servicios => 'Tu tiempo y tu trabajo. Llevan agenda y capacidad, no inventario.',
            self::Ambos => 'Vendes cosas y además das servicios. Cada uno con su flujo.',
        };
    }

    public function hasProducts(): bool
    {
        return $this === self::Productos || $this === self::Ambos;
    }

    public function hasServices(): bool
    {
        return $this === self::Servicios || $this === self::Ambos;
    }
}
