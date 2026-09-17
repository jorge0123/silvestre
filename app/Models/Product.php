<?php

namespace App\Models;

use App\Enums\PriceMode;
use App\Enums\StockMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Fillable([
    'name', 'slug', 'description', 'stock_mode', 'price_mode',
    'capacity_per_day', 'lead_time_days', 'is_active', 'position',
])]
class Product extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'stock_mode' => StockMode::class,
            'price_mode' => PriceMode::class,
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('position');
    }

    public function defaultVariant(): ?ProductVariant
    {
        return $this->variants->first() ?? $this->variants()->first();
    }

    /** Precio "desde": el más bajo entre sus variantes activas. */
    public function fromPriceCents(): ?int
    {
        return $this->variants->where('is_active', true)->min('price_cents');
    }

    public function formattedPrice(): string
    {
        return $this->price_mode->format($this->fromPriceCents());
    }

    /**
     * Disponibilidad según el modo de existencia. Por encargo no mira stock:
     * mira cuántos lugares quedan en la capacidad del día.
     */
    public function isAvailable(?int $qty = 1): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($this->stock_mode) {
            StockMode::Unlimited => true,
            StockMode::MadeToOrder => $this->remainingCapacityToday() >= $qty,
            StockMode::Inventory => $this->variants->contains(
                fn (ProductVariant $v) => $v->availableStock() >= $qty
            ),
        };
    }

    /** Lugares libres hoy para un producto por encargo. */
    public function remainingCapacityToday(): int
    {
        if (! $this->stock_mode->usesCapacity() || $this->capacity_per_day === null) {
            return PHP_INT_MAX;
        }

        $used = OrderItem::query()
            ->whereHas('variant', fn ($q) => $q->where('product_id', $this->id))
            ->whereHas('order', fn ($q) => $q->whereDate('placed_at', today())
                ->whereNotIn('state', ['cancelled', 'refunded', 'expired']))
            ->sum('qty');

        return max(0, $this->capacity_per_day - (int) $used);
    }

    /** La fecha más temprana en que este producto puede entregarse. */
    public function earliestFulfillmentDate(): Carbon
    {
        return now()->addDays($this->lead_time_days ?? 0);
    }
}
