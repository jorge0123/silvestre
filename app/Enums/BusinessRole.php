<?php

namespace App\Enums;

/**
 * Quién puede hacer qué dentro de un negocio.
 *
 * Solo aplica de verdad en Empresa. En Negocio siempre hay un único Owner.
 * La regla que importa: un vendedor no debe poder ver el margen ni mover stock.
 */
enum BusinessRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Seller = 'seller';
    case Warehouse = 'warehouse';
    case Accountant = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Propietario',
            self::Admin => 'Administrador',
            self::Seller => 'Vendedor',
            self::Warehouse => 'Almacén',
            self::Accountant => 'Contador',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Todo, incluido el plan, el equipo y cerrar el negocio.',
            self::Admin => 'Todo menos facturación, plan y equipo.',
            self::Seller => 'Solo pedidos y citas. No ve costos ni márgenes.',
            self::Warehouse => 'Solo inventario y movimientos de stock.',
            self::Accountant => 'Solo lectura de ingresos y estado de cuenta.',
        };
    }

    /** Permisos como lista plana; las Policies los consultan. */
    public function abilities(): array
    {
        return match ($this) {
            self::Owner => ['*'],
            self::Admin => [
                'catalog.manage', 'orders.manage', 'stock.manage',
                'posts.manage', 'income.view', 'profile.manage',
            ],
            self::Seller => ['orders.manage', 'catalog.view'],
            self::Warehouse => ['stock.manage', 'catalog.view'],
            self::Accountant => ['income.view'],
        };
    }

    public function can(string $ability): bool
    {
        $abilities = $this->abilities();

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }
}
