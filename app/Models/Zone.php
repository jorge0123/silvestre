<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Zona de la ciudad. Responde "¿quién entrega en mi colonia?". */
class Zone extends Model
{
    protected $guarded = [];

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class);
    }
}
