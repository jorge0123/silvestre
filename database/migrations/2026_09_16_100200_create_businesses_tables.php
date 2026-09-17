<?php

use App\Enums\BusinessKind;
use App\Enums\BusinessRole;
use App\Enums\Offering;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('slug')->unique();           // @galletasdechela
            $table->string('name');
            $table->string('kind')->default(BusinessKind::Negocio->value);
            $table->string('offering')->default(Offering::Productos->value);

            // La "presentación" del perfil: dos líneas que explican quién eres.
            $table->string('intro', 160)->nullable();
            $table->text('about')->nullable();

            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('whatsapp')->nullable();

            // Dirección física: la necesitan pickup y los servicios en local.
            $table->string('address')->nullable();
            $table->string('address_notes')->nullable();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city')->nullable();

            // Solo Empresa
            $table->string('legal_name')->nullable();
            $table->string('tax_id')->nullable();

            // Un perfil a medio llenar es peor que no tenerlo: bajo 60 no se
            // indexa en búsqueda. Al llegar a 100 arranca la prueba de 3 meses.
            $table->unsignedTinyInteger('activation_score')->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_verified')->default(false);

            // Desnormalizado a propósito: ordenar el directorio con un AVG en
            // vivo no escala. rating_bayes es el que ordena de verdad.
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('rating_sum')->default(0);
            $table->decimal('rating_bayes', 3, 2)->default(0);
            $table->unsignedInteger('followers_count')->default(0);
            $table->timestamp('last_posted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'rating_bayes']);
            $table->index(['zone_id', 'rating_bayes']);
        });

        // Un usuario puede pertenecer a varios negocios y un negocio (Empresa)
        // puede tener varios usuarios. En Negocio siempre hay un solo Owner.
        Schema::create('business_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default(BusinessRole::Owner->value);
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'user_id']);
        });

        // Horario de atención. Sirve para "Abierto ahora" y para validar que
        // una cita no caiga fuera de horario.
        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');  // 0 domingo … 6 sábado
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();

            $table->index(['business_id', 'weekday']);
        });

        // Qué métodos de entrega de PRODUCTOS acepta. El negocio activa los que
        // quiera; `config` guarda lo específico de cada uno (tarifa de envío,
        // envío gratis desde X, etc.).
        Schema::create('business_fulfillment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'method']);
        });

        // Lo mismo para SERVICIOS: en local, a domicilio, en línea.
        Schema::create('business_service_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('mode');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('travel_fee_cents')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'mode']);
        });

        // Zonas propias de entrega, con su costo y su mínimo. Solo aplican a
        // local_delivery y a servicios a domicilio.
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('fee_cents')->default(0);
            $table->unsignedInteger('min_order_cents')->default(0);
            $table->unsignedSmallInteger('eta_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('business_service_modes');
        Schema::dropIfExists('business_fulfillment');
        Schema::dropIfExists('business_hours');
        Schema::dropIfExists('business_users');
        Schema::dropIfExists('businesses');
    }
};
