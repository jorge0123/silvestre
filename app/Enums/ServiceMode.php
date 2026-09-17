<?php

namespace App\Enums;

/**
 * Dónde se presta un SERVICIO.
 *
 * Es el equivalente de FulfillmentMethod para el lado de servicios, y el negocio
 * también puede activar varios a la vez.
 */
enum ServiceMode: string
{
    case AtBusiness = 'at_business';
    case AtCustomer = 'at_customer';
    case Remote = 'remote';

    public function label(): string
    {
        return match ($this) {
            self::AtBusiness => 'En mi local',
            self::AtCustomer => 'A domicilio',
            self::Remote => 'En línea',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AtBusiness => 'El cliente viene a tu dirección.',
            self::AtCustomer => 'Tú vas a donde está el cliente.',
            self::Remote => 'Videollamada o trabajo remoto.',
        };
    }

    public function requires(): array
    {
        return match ($this) {
            self::AtBusiness => ['address', 'hours'],
            self::AtCustomer => ['zones', 'hours'],
            self::Remote => ['hours'],
        };
    }
}
