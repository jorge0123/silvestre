<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El precio y el costo viven aquí, no en el producto.
 *
 * stock_cached es solo una caché del libro de movimientos: la fuente de verdad
 * siempre es la suma de stock_movements.
 */
#[Fillable([
    'sku', 'name', 'price_cents', 'cost_cents',
    'low_stock_threshold', 'is_active', 'position',
])]
class ProductVariant extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // --------------------------------------------------------------- inventario

    /** Existencia física real: la suma del libro. Nunca se lee de la caché. */
    public function physicalStock(): int
    {
        return (int) $this->movements()
            ->whereNotIn('type', [
                StockMovementType::Reservation->value,
                StockMovementType::Release->value,
            ])
            ->sum('qty');
    }

    /** Piezas apartadas por pedidos sin pagar cuya reserva sigue viva. */
    public function reservedStock(): int
    {
        return (int) abs($this->movements()
            ->where('type', StockMovementType::Reservation)
            ->whereNull('released_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->sum('qty'));
    }

    /** Lo que de verdad se puede vender ahora mismo. */
    public function availableStock(): int
    {
        return $this->physicalStock() - $this->reservedStock();
    }

    public function isLowStock(): bool
    {
        return $this->low_stock_threshold > 0
            && $this->availableStock() <= $this->low_stock_threshold;
    }

    /** Sincroniza la caché tras cualquier movimiento. */
    public function syncStockCache(): void
    {
        $this->forceFill([
            'stock_cached' => $this->physicalStock(),
            'reserved_cached' => $this->reservedStock(),
        ])->save();
    }

    // ------------------------------------------------------------------ margen

    public function marginCents(): ?int
    {
        return $this->cost_cents === null ? null : $this->price_cents - $this->cost_cents;
    }

    /** Lo que ninguna red social le dice a un emprendedor: si gana o no. */
    public function marginPercent(): ?float
    {
        if ($this->cost_cents === null || $this->price_cents === 0) {
            return null;
        }

        return round((($this->price_cents - $this->cost_cents) / $this->price_cents) * 100, 1);
    }
}
