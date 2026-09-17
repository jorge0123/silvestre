<?php

namespace App\Models;

use App\Enums\ServiceMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dónde atiende el negocio: en local, a domicilio o en línea. */
class BusinessServiceMode extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['mode' => ServiceMode::class, 'is_active' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
