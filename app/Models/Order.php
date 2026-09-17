<?php

namespace App\Models;

use App\Enums\BookingState;
use App\Enums\FulfillmentMethod;
use App\Enums\OrderState;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\ServiceMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Pedidos de producto y citas de servicio comparten esta tabla porque comparten
 * cliente, negocio, pago, estado de cuenta y reseña. Lo que cambia es la
 * máquina de estados: OrderState para productos, BookingState (en la fila
 * complementaria `bookings`) para servicios.
 */
class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'state' => OrderState::class,
            'placed_at' => 'datetime',
            'completed_at' => 'datetime',
            'payment_expires_at' => 'datetime',
            'payment_submitted_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'payment_method' => PaymentMethod::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->public_code ??= self::generatePublicCode();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_code';
    }

    /** Folio corto y legible para dictarlo por teléfono. Sin 0/O ni 1/I. */
    public static function generatePublicCode(): string
    {
        do {
            $code = 'SV-'.Str::upper(Str::random(6));
            $code = strtr($code, ['0' => 'X', 'O' => 'K', '1' => 'Y', 'I' => 'J']);
        } while (self::where('public_code', $code)->exists());

        return $code;
    }

    // ---------------------------------------------------------------- relaciones

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('happened_at');
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // ------------------------------------------------------------------ dominio

    public function isService(): bool
    {
        return $this->type === OrderType::Service;
    }

    /** El estado vigente, venga de la máquina de pedidos o de la de citas. */
    public function currentState(): OrderState|BookingState
    {
        return $this->isService()
            ? ($this->booking?->state ?? BookingState::Requested)
            : $this->state;
    }

    public function stateLabel(): string
    {
        return $this->currentState()->label();
    }

    public function stateTone(): string
    {
        return $this->currentState()->tone();
    }

    /**
     * La regla que hace que las estrellas de Silvestre valgan algo: solo se
     * puede reseñar un pedido realmente entregado (o un servicio completado).
     */
    public function unlocksReview(): bool
    {
        return $this->currentState()->unlocksReview();
    }

    public function fulfillmentMethod(): ?FulfillmentMethod
    {
        return $this->fulfillment_method
            ? FulfillmentMethod::from($this->fulfillment_method)
            : null;
    }

    public function serviceMode(): ?ServiceMode
    {
        return $this->service_mode ? ServiceMode::from($this->service_mode) : null;
    }

    public function marginCents(): int
    {
        return $this->subtotal_cents - $this->cost_cents;
    }

    /** Total con el símbolo de la moneda del pedido (Q en Guatemala). */
    public function formattedTotal(): string
    {
        return config('silvestre.currency.symbol', 'Q').number_format($this->total_cents / 100, 2);
    }

    /** URL pública de seguimiento: se abre sin cuenta. */
    public function trackingUrl(): string
    {
        return url('/p/'.$this->public_code);
    }

    public function isPaymentExpired(): bool
    {
        return in_array($this->state, [OrderState::PendingPayment, OrderState::PaymentSubmitted], true)
            && $this->payment_expires_at !== null
            && $this->payment_expires_at->isPast();
    }
}
