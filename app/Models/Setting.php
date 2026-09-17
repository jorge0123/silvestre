<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Ajustes globales de la plataforma, editables desde el panel de administración. */
class Setting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting.{$key}", function () use ($key, $default) {
            $row = self::where('key', $key)->first();

            return $row ? $row->value : $default;
        });
    }

    public static function set(string $key, mixed $value, ?User $by = null): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $by?->id]);
        Cache::forget("setting.{$key}");
    }
}
