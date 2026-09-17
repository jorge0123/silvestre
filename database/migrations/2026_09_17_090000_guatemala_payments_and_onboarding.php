<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decisiones de arranque:
 *  - Lanzamiento en Ciudad de Guatemala, catálogos en quetzales.
 *  - Silvestre no procesa ventas: el cliente le paga directo al negocio.
 *  - El único ingreso de Silvestre son las suscripciones, en dólares.
 *  - Onboarding por pasos, que se puede retomar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Equipo de Silvestre: aprueba pagos de suscripción y modera.
            $table->boolean('is_admin')->default(false)->after('account_type');

            // Modo en el que está usando la app. null = modo personal (comprar,
            // seguir, reseñar). Un id = está administrando ese negocio. Una
            // misma cuenta cambia de modo sin cerrar sesión ni tener dos cuentas.
            $table->foreignId('active_business_id')->nullable()->after('is_admin')
                ->constrained('businesses')->nullOnDelete();

            // Guías de la app que ya vio o cerró, para no repetirlas.
            $table->json('dismissed_guides')->nullable();
        });

        Schema::table('businesses', function (Blueprint $table) {
            // Paso del onboarding en el que se quedó, para poder retomarlo.
            $table->string('onboarding_step', 20)->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
        });

        // Cómo le pagan los clientes a ESTE negocio. `details` guarda los datos
        // que el cliente necesita para pagar (banco y cuenta, número de
        // billetera, link). Silvestre solo los muestra.
        Schema::create('business_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->boolean('is_active')->default(true);
            $table->json('details')->nullable();
            $table->string('instructions', 240)->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'method']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->char('currency', 3)->default('GTQ')->after('total_cents');

            // Comprobante que sube el cliente y confirmación del negocio.
            $table->string('payment_proof_path')->nullable()->after('payment_expires_at');
            $table->timestamp('payment_submitted_at')->nullable()->after('payment_proof_path');
            $table->timestamp('payment_confirmed_at')->nullable()->after('payment_submitted_at');
            $table->foreignId('payment_confirmed_by')->nullable()->after('payment_confirmed_at')
                ->constrained('users')->nullOnDelete();
        });

        // Precios por duración: Pro cuesta $3 por 1 mes o $6 por 3 meses.
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('interval_months');
            $table->unsignedInteger('price_cents');
            $table->char('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plan_id', 'interval_months']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('plan_price_id')->nullable()->after('plan_id')
                ->constrained()->nullOnDelete();
            $table->timestamp('current_period_ends_at')->nullable()->after('trial_ends_at');
        });

        // Pagos de suscripción (negocio → Silvestre). Mientras el cobro sea
        // manual, cada pago trae comprobante y lo aprueba el equipo.
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_price_id')->constrained();

            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->string('proof_path')->nullable();

            $table->string('state')->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Periodo que cubre este pago, fijado al aprobarlo.
            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();

            $table->timestamps();

            $table->index(['state', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_price_id');
            $table->dropColumn('current_period_ends_at');
        });
        Schema::dropIfExists('plan_prices');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_confirmed_by');
            $table->dropColumn(['currency', 'payment_proof_path', 'payment_submitted_at', 'payment_confirmed_at']);
        });
        Schema::dropIfExists('business_payment_methods');
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['onboarding_step', 'onboarding_completed_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_business_id');
            $table->dropColumn(['is_admin', 'dismissed_guides']);
        });
    }
};
