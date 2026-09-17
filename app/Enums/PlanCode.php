<?php

namespace App\Enums;

/**
 * Los planes.
 *
 * Filosofía: TODAS las funciones son gratis para todos. Un emprendedor puede
 * vender, cobrar, agendar, publicar y usar historias sin pagar nunca. Los
 * límites gratuitos están pensados para que un negocio pequeño no los alcance.
 *
 * Pro no desbloquea funciones: compra ALCANCE (salir como "Promocionado" en el
 * Inicio de otras personas) y ESCALA (sin topes de volumen). Por eso puede ser
 * barato: lo paga quien ya está creciendo, no quien está empezando.
 *
 * Los topes viven en la tabla `plans` y los precios en `plan_prices`, para
 * poder cambiarlos sin desplegar.
 */
enum PlanCode: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Empresa = 'empresa';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Gratis',
            self::Pro => 'Pro',
            self::Empresa => 'Empresa',
        };
    }

    /** Topes y capacidades con los que se siembra la tabla `plans`. null = sin límite. */
    public function defaults(): array
    {
        $everything = [
            'can_checkout' => true,
            'can_reserve_stock' => true,
            'can_use_team' => true,
            'can_invoice' => false, // FEL aún no existe para nadie
        ];

        return match ($this) {
            self::Free => [
                ...$everything,
                'description' => 'Todas las funciones, con topes pensados para negocios que empiezan.',
                'can_promote' => false,
                'post_quota_weekly' => 7,
                'story_quota_daily' => 10,
                'photo_limit' => 10,
                'product_limit' => 30,
                'service_limit' => 30,
                'is_active' => true,
            ],
            self::Pro => [
                ...$everything,
                'description' => 'Alcance y escala: sale como Promocionado y sin topes.',
                'can_promote' => true,
                'post_quota_weekly' => null,
                'story_quota_daily' => null,
                'photo_limit' => 20,
                'product_limit' => null,
                'service_limit' => null,
                'is_active' => true,
            ],
            // Absorbido por Pro: ya no hay funciones exclusivas que vender.
            self::Empresa => [
                ...$everything,
                'description' => 'Reservado para equipos grandes.',
                'can_promote' => true,
                'post_quota_weekly' => null,
                'story_quota_daily' => null,
                'photo_limit' => 20,
                'product_limit' => null,
                'service_limit' => null,
                'is_active' => false,
            ],
        };
    }

    /**
     * Precios en centavos de dólar, por duración en meses.
     * Pro: $4 al mes, o $8 por 3 meses (sale a $2.67 al mes).
     *
     * @return array<int, int>
     */
    public function prices(): array
    {
        return match ($this) {
            self::Free => [],
            self::Pro => [1 => 400, 3 => 800],
            self::Empresa => [],
        };
    }

    /** Qué gana alguien al pasar a este plan, en palabras de la gente. */
    public function benefits(): array
    {
        return match ($this) {
            self::Free => [
                'Todas las funciones: vender, cobrar, citas, historias y publicaciones',
                '7 publicaciones por semana y 10 historias al día',
                'Hasta 30 productos y 30 servicios, 10 fotos por publicación',
            ],
            self::Pro => [
                'Tus publicaciones salen como Promocionado en el Inicio de más personas',
                'Sin límite de publicaciones, historias, productos ni servicios',
                'Hasta 20 fotos o videos por publicación',
            ],
            self::Empresa => [],
        };
    }
}
