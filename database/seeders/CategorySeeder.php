<?php

namespace Database\Seeders;

use App\Enums\Offering;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Categorías del directorio, con los documentos que exige Guatemala.
     *
     * `offering` limita quién puede elegirla: alguien que solo vende productos
     * no puede ponerse en "Reparaciones y oficios". `requires_verification`
     * marca las que no se publican sin comprobante.
     *
     * ADVERTENCIA: los nombres de los documentos son una guía. Antes de abrir
     * al público, confirma con un abogado o contador en Guatemala qué exige
     * hoy cada autoridad (MSPAS, colegios profesionales, SIB).
     */
    public function run(): void
    {
        $rows = [
            ['Repostería y panadería', Offering::Productos, true, 'Licencia sanitaria (MSPAS)'],
            ['Comida casera', Offering::Productos, true, 'Licencia sanitaria (MSPAS)'],
            ['Tienda y abarrotes', Offering::Productos, false, null],
            ['Artesanía', Offering::Productos, false, null],
            ['Ropa y accesorios', Offering::Productos, false, null],
            ['Plantas y jardín', Offering::Productos, false, null],
            ['Papelería y regalos', Offering::Productos, false, null],

            ['Belleza y cuidado personal', Offering::Ambos, false, null],
            ['Estética avanzada', Offering::Servicios, true, 'Colegiado activo y licencia sanitaria'],
            ['Salud y bienestar', Offering::Servicios, true, 'Constancia de colegiado activo'],
            ['Servicios del hogar', Offering::Servicios, false, null],
            ['Reparaciones y oficios', Offering::Servicios, false, null],
            ['Eventos y banquetes', Offering::Ambos, true, 'Licencia sanitaria (MSPAS)'],
            ['Clases y tutorías', Offering::Servicios, false, null],
            ['Servicios profesionales', Offering::Servicios, true, 'Constancia de colegiado activo'],
            ['Tecnología y reparación', Offering::Ambos, false, null],
            ['Mascotas', Offering::Ambos, true, 'Constancia de médico veterinario colegiado'],
            ['Transporte y mudanzas', Offering::Servicios, false, null],
        ];

        foreach ($rows as $i => [$name, $offering, $needsDoc, $doc]) {
            Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'offering' => $offering->value,
                    'requires_verification' => $needsDoc,
                    'required_document' => $doc,
                    'position' => $i,
                    'is_active' => true,
                ]
            );
        }
    }
}
