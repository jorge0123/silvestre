<?php

use App\Enums\SubscriptionState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Los topes son DATOS, no constantes en el código: así se cambia un
        // plan sin desplegar. null significa ilimitado.
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('price_cents')->default(0);

            $table->unsignedSmallInteger('post_quota_weekly')->nullable();
            $table->unsignedSmallInteger('photo_limit')->default(7);
            $table->unsignedSmallInteger('product_limit')->nullable();
            $table->unsignedSmallInteger('service_limit')->nullable();

            $table->boolean('can_checkout')->default(false);
            $table->boolean('can_reserve_stock')->default(false);
            $table->boolean('can_use_team')->default(false);
            $table->boolean('can_invoice')->default(false);

            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('state')->default(SubscriptionState::Trial->value);

            // La prueba arranca al ACTIVAR el perfil, no al registrarse: si
            // corriera desde el registro se le iría la mitad averiguando cómo
            // subir una foto, y la prueba no demostraría nada.
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->index(['business_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
