<?php

namespace Database\Seeders;

use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZoneSeeder extends Seeder
{
    /**
     * Zonas de lanzamiento: Ciudad de Guatemala y su área metropolitana.
     *
     * La ciudad ya está dividida en zonas numeradas, así que "¿quién entrega
     * en la zona 10?" es exactamente como la gente busca. Las zonas 20, 22 y
     * 23 no existen dentro del municipio. Se lanza en UNA ciudad: un
     * directorio con diez negocios repartidos por todo el país no le sirve a
     * nadie.
     */
    public function run(): void
    {
        $city = config('silvestre.city', 'Guatemala');

        $zones = collect([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 21, 24, 25])
            ->map(fn (int $n) => "Zona {$n}")
            ->merge([
                'Mixco', 'Villa Nueva', 'San Miguel Petapa', 'Santa Catarina Pinula',
                'San José Pinula', 'Fraijanes', 'Villa Canales', 'Chinautla', 'Amatitlán',
            ]);

        // Las zonas de pruebas anteriores (de otra ciudad) ya no aplican.
        Zone::where('city', '!=', $city)->delete();

        foreach ($zones as $i => $name) {
            Zone::updateOrCreate(
                ['city' => $city, 'slug' => Str::slug($name)],
                ['name' => $name, 'position' => $i]
            );
        }
    }
}
