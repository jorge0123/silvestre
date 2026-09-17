<?php

namespace App\Enums;

/**
 * Qué es una cuenta al registrarse.
 *
 * Una cuenta personal puede crear un negocio más tarde sin abrir otra cuenta:
 * el vínculo vive en business_users, no aquí. Este campo solo decide a dónde
 * mandamos al usuario después del registro.
 */
enum AccountType: string
{
    case Personal = 'personal';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Cuenta personal',
            self::Business => 'Cuenta de negocio',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Personal => 'Compra, sigue negocios y deja reseñas.',
            self::Business => 'Publica tu catálogo, recibe pedidos y cobra.',
        };
    }
}
