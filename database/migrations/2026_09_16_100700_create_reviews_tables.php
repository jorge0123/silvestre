<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Exigir un pedido entregado es el antifraude más fuerte que existe:
            // no se pueden falsificar estrellas sin pagar un pedido real.
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedTinyInteger('stars');
            $table->text('body')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Una reseña por persona por negocio. Editable, nunca duplicable.
            // La regla vive en el índice, no en un if de un controlador.
            $table->unique(['business_id', 'user_id']);
            $table->index(['business_id', 'stars']);
        });

        // El negocio responde UNA vez y no puede borrar la reseña.
        Schema::create('review_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_replies');
        Schema::dropIfExists('reviews');
    }
};
