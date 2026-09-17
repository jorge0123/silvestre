<?php

namespace App\Enums;

/**
 * Persona física contra persona moral.
 *
 * No es una etiqueta decorativa: Empresa exige NIT y razón social, habilita
 * equipo con roles, sucursales y facturación FEL. Negocio es una sola persona.
 */
enum BusinessKind: string
{
    case Negocio = 'negocio';
    case Empresa = 'empresa';

    public function label(): string
    {
        return match ($this) {
            self::Negocio => 'Negocio',
            self::Empresa => 'Empresa',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Negocio => 'Eres tú. Emprendimiento personal, sin NIT obligatorio ni equipo.',
            self::Empresa => 'Razón social y NIT, equipo con roles, sucursales y facturación FEL.',
        };
    }

    /** Solo Empresa puede invitar a otras personas al negocio. */
    public function allowsTeam(): bool
    {
        return $this === self::Empresa;
    }

    public function requiresTaxId(): bool
    {
        return $this === self::Empresa;
    }
}
