<?php

namespace Database\Seeders;

use App\Enums\PlanCode;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Crea los planes base si no existen. Si ya existen NO los toca: desde que
     * hay panel de administración, los precios y topes se cambian ahí, y volver
     * a sembrar no debe pisar esas decisiones.
     */
    public function run(): void
    {
        foreach (PlanCode::cases() as $i => $code) {
            $plan = Plan::firstOrCreate(
                ['code' => $code->value],
                [
                    'name' => $code->label(),
                    'position' => $i,
                    'price_cents' => 0, // los precios viven en plan_prices
                    ...$code->defaults(),
                ]
            );

            if ($plan->wasRecentlyCreated) {
                foreach ($code->prices() as $months => $cents) {
                    $plan->prices()->create(['interval_months' => $months, 'price_cents' => $cents, 'currency' => 'USD', 'is_active' => true]);
                }
            }
        }
    }
}
