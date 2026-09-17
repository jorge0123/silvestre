<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Impulso: compra visibilidad, nunca reputación. Todo lo que sale de
        // aquí se renderiza siempre con la etiqueta "Promocionado".
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('price_cents')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->morphs('reportable');
            $table->string('reason');
            $table->text('detail')->nullable();
            $table->string('state')->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('state');
        });

        // Agregado nocturno. Es el panel del negocio y, sumado, el único KPI
        // que de verdad importa: negocios que publican y venden cada semana.
        Schema::create('business_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('profile_views')->default(0);
            $table->unsignedInteger('whatsapp_clicks')->default(0);
            $table->unsignedInteger('new_followers')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('revenue_cents')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_daily_stats');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('promotions');
    }
};
