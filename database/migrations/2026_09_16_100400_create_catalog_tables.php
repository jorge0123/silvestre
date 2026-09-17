<?php

use App\Enums\PriceMode;
use App\Enums\StockMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- PRODUCTOS ----
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('stock_mode')->default(StockMode::Inventory->value);
            $table->string('price_mode')->default(PriceMode::Fixed->value);

            // Solo para made_to_order: no hay stock, hay capacidad.
            $table->unsignedSmallInteger('capacity_per_day')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'slug']);
        });

        // El precio y el costo viven en la VARIANTE, no en el producto: sabor,
        // tamaño y presentación cuestan distinto. Un producto sin variantes
        // reales lleva una sola variante "única".
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->string('name')->default('Única');

            $table->unsignedInteger('price_cents')->default(0);
            // cost_cents es lo que habilita el margen real. Es opcional, pero
            // es el argumento de venta más fuerte del plan Pro.
            $table->unsignedInteger('cost_cents')->nullable();

            // Caché del libro de movimientos, nunca la fuente de verdad.
            $table->integer('stock_cached')->default(0);
            $table->integer('reserved_cached')->default(0);
            $table->unsignedSmallInteger('low_stock_threshold')->default(0);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('webp_path')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // ---- SERVICIOS ----
        // No tienen stock ni variantes: tienen duración, capacidad y modalidad.
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('price_mode')->default(PriceMode::Fixed->value);
            $table->unsignedInteger('price_cents')->nullable();

            $table->unsignedSmallInteger('duration_min')->default(60);
            $table->unsignedSmallInteger('buffer_min')->default(0);
            $table->unsignedSmallInteger('capacity_per_day')->nullable();
            $table->unsignedSmallInteger('min_notice_hours')->default(24);

            // Subconjunto de los modos que el negocio tiene activos.
            $table->json('modes')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'slug']);
        });

        Schema::create('service_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('webp_path')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // ---- LIBRO DE INVENTARIO ----
        // El stock nunca se edita: se mueve. La existencia es la suma de aquí.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->integer('qty');
            $table->string('reason')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();

            // Una reserva de OXXO caduca sola y libera el inventario.
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->timestamps();

            $table->index(['product_variant_id', 'created_at']);
            $table->index(['expires_at', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('service_media');
        Schema::dropIfExists('services');
        Schema::dropIfExists('product_media');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
