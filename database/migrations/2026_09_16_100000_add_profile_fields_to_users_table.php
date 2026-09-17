<?php

use App\Enums\AccountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Personal o business. Solo decide a dónde va tras registrarse:
            // el vínculo real con un negocio vive en business_users.
            $table->string('account_type')->default(AccountType::Personal->value)->after('email');

            $table->string('username')->nullable()->unique()->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('avatar_path')->nullable();

            // Antifraude de reseñas: una cuenta recién creada no califica.
            // Se compara contra created_at, así que no hace falta columna extra,
            // pero sí marcamos cuándo quedó habilitada por si cambiamos la regla.
            $table->timestamp('review_enabled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'account_type', 'username', 'phone', 'phone_verified_at',
                'avatar_path', 'review_enabled_at',
            ]);
        });
    }
};
