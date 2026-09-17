<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * UN CARRITO POR NEGOCIO.
 *
 * No se mezclan productos de dos negocios en un mismo pedido: son dos
 * vendedores, dos entregas, dos destinos de dinero y dos responsabilidades.
 * Si el usuario agrega de un segundo negocio, se abre un segundo carrito.
 */
class Cart extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function subtotalCents(): int
    {
        return (int) $this->items->sum(fn (CartItem $i) => $i->qty * $i->unit_price_cents);
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('qty');
    }

    /**
     * Líneas cuyo precio cambió desde que se agregaron. Se le avisa al cliente
     * antes de cobrar, en lugar de cobrarle de más en silencio.
     *
     * @return Collection<int, CartItem>
     */
    public function stalePriceItems()
    {
        return $this->items->filter(
            fn (CartItem $i) => $i->variant && $i->variant->price_cents !== $i->unit_price_cents
        );
    }

    /** Líneas que ya no alcanzan existencia. Se revalida siempre al pagar. */
    public function unavailableItems()
    {
        return $this->items->filter(
            fn (CartItem $i) => ! $i->variant || $i->variant->availableStock() < $i->qty
        );
    }
}
