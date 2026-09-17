<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Interruptores globales de Silvestre.
 */
class Platform
{
    /**
     * Tope técnico de archivos por publicación, aunque no haya límite de plan:
     * protege al servidor, no es una restricción comercial.
     */
    public const MAX_MEDIA_PER_POST = 30;

    /**
     * "Todo libre": mientras esté encendido, nadie tiene límites de
     * publicaciones, historias, fotos ni catálogo, sin importar su plan.
     * Los planes siguen existiendo (y "Promocionado" sigue funcionando), pero
     * sus topes no se aplican.
     */
    public static function freeMode(): bool
    {
        return (bool) Setting::get('free_mode', false);
    }
}
