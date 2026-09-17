<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Categorías del directorio. `offering` limita qué categorías puede
        // elegir un negocio según lo que vende: "Plomería" no acepta productos.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('offering')->default('ambos');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Zonas de la ciudad, compartidas por toda la plataforma. Sirven para
        // "¿quién entrega en mi colonia?", que es la búsqueda que no responde
        // ninguna red social. Sin polígonos: en v1 basta el nombre.
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('city');
            $table->string('slug');
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['city', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
        Schema::dropIfExists('categories');
    }
};
