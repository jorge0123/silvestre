<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo SOLO para desarrollo local. Nunca se corre en producción.
 * Contraseña de todas las cuentas: secret123
 *
 *   ana@test.gt     cliente, sigue varios negocios y ve publicidad Pro
 *   chela@test.mx   dueña de Galletas de la Abuela Chela (Pro en prueba)
 *   kari@test.gt    dueña de Uñas por Kari (Pro pagado)
 *   rosa@test.gt    dueña de Vivero Las Orquídeas (Gratis)
 *   nuevo@test.gt   se registró para vender y aún no abre su negocio
 *   admin@test.gt   equipo de Silvestre (panel de administración)
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        User::updateOrCreate(['email' => 'nuevo@test.gt'], [
            'name' => 'Luis Coc', 'password' => 'secret123',
            'account_type' => AccountType::Business,
        ])->forceFill(['email_verified_at' => now()])->save();

        // Equipo de Silvestre: entra al panel de administración.
        User::updateOrCreate(['email' => 'admin@test.gt'], [
            'name' => 'Equipo Silvestre', 'password' => 'secret123',
            'account_type' => AccountType::Personal,
        ])->forceFill(['email_verified_at' => now(), 'is_admin' => true])->save();

        $this->call(ShowcaseSeeder::class);
    }
}
