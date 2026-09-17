<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Administración de la plataforma:
 *  - Ajustes globales (por ejemplo, "Todo libre").
 *  - Planes editables desde el panel: descripción, "Promocionado" como
 *    capacidad del plan, y fotos por publicación sin límite (null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->string('description', 255)->nullable()->after('name');
            // Si los negocios de este plan salen como "Promocionado" en el Inicio.
            $table->boolean('can_promote')->default(false)->after('can_invoice');
            $table->unsignedSmallInteger('photo_limit')->nullable()->default(null)->change();
        });

        DB::table('plans')->whereIn('code', ['pro', 'empresa'])->update(['can_promote' => true]);

        // Negocio fundador: llegó mientras "Todo libre" estaba encendido y
        // conserva todo sin límites aunque después se apague.
        Schema::table('businesses', function (Blueprint $table) {
            $table->timestamp('founder_at')->nullable()->after('activated_at');
        });

        // Condiciones congeladas: límites, funciones y precio del plan el día
        // en que el negocio entró. Cambiar el plan después no le afecta.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->json('terms')->nullable()->after('plan_id');
        });

        // Por el momento, todo libre: sin límites para nadie.
        DB::table('settings')->insert([
            'key' => 'free_mode',
            'value' => json_encode(true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Todo lo que ya existe llegó con "Todo libre": son fundadores y sus
        // suscripciones quedan con las condiciones de hoy.
        DB::table('businesses')->whereNull('founder_at')->update(['founder_at' => now()]);

        $plans = DB::table('plans')->get()->keyBy('id');
        $prices = DB::table('plan_prices')->get()->keyBy('id');

        foreach (DB::table('subscriptions')->whereNull('terms')->get() as $sub) {
            $plan = $plans[$sub->plan_id] ?? null;
            if (! $plan) {
                continue;
            }

            $price = $sub->plan_price_id ? ($prices[$sub->plan_price_id] ?? null) : null;

            DB::table('subscriptions')->where('id', $sub->id)->update(['terms' => json_encode([
                'post_quota_weekly' => $plan->post_quota_weekly,
                'story_quota_daily' => $plan->story_quota_daily,
                'photo_limit' => $plan->photo_limit,
                'product_limit' => $plan->product_limit,
                'service_limit' => $plan->service_limit,
                'can_checkout' => (bool) $plan->can_checkout,
                'can_reserve_stock' => (bool) $plan->can_reserve_stock,
                'can_use_team' => (bool) $plan->can_use_team,
                'can_invoice' => (bool) $plan->can_invoice,
                'can_promote' => (bool) $plan->can_promote,
                'price_cents' => $price?->price_cents,
                'interval_months' => $price?->interval_months,
            ])]);
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('terms');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('founder_at');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['description', 'can_promote']);
        });

        Schema::dropIfExists('settings');
    }
};
