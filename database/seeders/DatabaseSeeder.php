<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            CategorySeeder::class,
            ZoneSeeder::class,
        ]);

        // Usuarios y negocio de ejemplo, solo fuera de producción.
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
