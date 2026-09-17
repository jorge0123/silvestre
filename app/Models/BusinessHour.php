<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class BusinessHour extends Model
{
    protected $guarded = [];

    public const WEEKDAYS = [
        0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
        4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function weekdayName(): string
    {
        return self::WEEKDAYS[$this->weekday] ?? '';
    }

    public function coversNow(?Carbon $at = null): bool
    {
        $at ??= now();

        if ((int) $at->dayOfWeek !== (int) $this->weekday) {
            return false;
        }

        $time = $at->format('H:i:s');

        return $time >= $this->opens_at && $time <= $this->closes_at;
    }
}
