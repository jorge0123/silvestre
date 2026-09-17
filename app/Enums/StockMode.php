<?php

namespace App\Enums;

/**
 * Los tres modos de existencia de un producto.
 *
 * Meter todo en una columna "cantidad" es el error clásico: una galleta por
 * encargo no tiene stock, tiene capacidad diaria.
 */
enum StockMode: string
{
    case Inventory = 'inventory';
    case MadeToOrder = 'made_to_order';
    case Unlimited = 'unlimited';

    public function label(): string
    {
        return match ($this) {
            self::Inventory => 'Inventario',
            self::MadeToOrder => 'Por encargo',
            self::Unlimited => 'Siempre disponible',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Inventory => 'Piezas contables que se descuentan al vender.',
            self::MadeToOrder => 'Sin stock: tiene capacidad por día y días de anticipación.',
            self::Unlimited => 'Digital o sin límite práctico.',
        };
    }

    /** Solo Inventory toca stock_movements. */
    public function tracksStock(): bool
    {
        return $this === self::Inventory;
    }

    public function usesCapacity(): bool
    {
        return $this === self::MadeToOrder;
    }
}
