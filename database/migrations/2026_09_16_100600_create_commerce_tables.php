<?php

use App\Enums\OrderState;
use App\Enums\OrderType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // UN CARRITO POR NEGOCIO. No se pueden mezclar galletas de un negocio
        // con tamales de otro: son dos vendedores, dos entregas, dos destinos
        // de dinero y dos responsabilidades distintas.
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_token')->nullable()->index();   // invitados
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'business_id']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('qty')->default(1);

            // El precio se congela al agregar. Si el negocio lo sube después,
            // se avisa antes de pagar en lugar de cobrar de más en silencio.
            $table->unsignedInteger('unit_price_cents');
            $table->timestamps();

            $table->unique(['cart_id', 'product_variant_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Folio público: se consulta sin cuenta en /p/{public_code}.
            $table->string('public_code', 16)->unique();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type')->default(OrderType::Product->value);
            $table->string('state')->default(OrderState::PendingPayment->value);

            // Datos de contacto congelados: si el cliente cambia su teléfono
            // mañana, el pedido de hoy debe seguir diciendo a dónde iba.
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();

            // Entrega (productos) o modalidad (servicios).
            $table->string('fulfillment_method')->nullable();
            $table->string('service_mode')->nullable();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('address_notes')->nullable();

            // Paquetería
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();

            // Los métodos de pago se definen en la siguiente etapa; por ahora
            // queda el campo para no tener que alterar la tabla después.
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('payment_expires_at')->nullable();

            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('delivery_fee_cents')->default(0);
            $table->unsignedInteger('discount_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            // Se copia de las variantes al momento de vender: sin esto no hay
            // margen histórico, porque el costo de un producto cambia.
            $table->unsignedInteger('cost_cents')->default(0);

            $table->text('notes')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'state']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();

            // Nombre congelado: el pedido debe poder leerse aunque el producto
            // se renombre o se borre del catálogo.
            $table->string('name');
            $table->string('variant_name')->nullable();
            $table->unsignedSmallInteger('qty')->default(1);
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('unit_cost_cents')->nullable();
            $table->timestamps();
        });

        // La línea de tiempo del tracking. Append-only: nunca se edita.
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('state');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->index(['order_id', 'happened_at']);
        });

        // Fila complementaria 1:1 con las órdenes de tipo servicio. Aquí vive
        // lo que un pedido de productos no necesita: agenda.
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('state');
            $table->string('mode');

            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedSmallInteger('duration_min')->default(60);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // Cotización: el negocio responde con un monto antes de confirmar.
            $table->unsignedInteger('quoted_cents')->nullable();
            $table->text('quote_notes')->nullable();

            $table->timestamps();

            $table->index(['service_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
