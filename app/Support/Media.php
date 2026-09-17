<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/** URLs públicas de fotos y videos guardados en el disco `public`. */
class Media
{
    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $url = Storage::disk('public')->url($path);

        // Si el disco apunta a este mismo servidor, se devuelve la ruta sin
        // dominio: así la foto se ve igual desde localhost, desde un enlace
        // temporal para demostrar o desde el dominio de producción. Un disco
        // externo (S3, CDN) sí conserva su URL completa.
        $host = parse_url($url, PHP_URL_HOST);

        return $host === null || $host === parse_url((string) config('app.url'), PHP_URL_HOST)
            ? (string) parse_url($url, PHP_URL_PATH)
            : $url;
    }

    public static function isVideo(?string $path): bool
    {
        return (bool) preg_match('/\.(mp4|webm|mov)$/i', (string) $path);
    }
}
