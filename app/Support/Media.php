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

        return Storage::disk('public')->url($path);
    }

    public static function isVideo(?string $path): bool
    {
        return (bool) preg_match('/\.(mp4|webm|mov)$/i', (string) $path);
    }
}
