<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_comments', function (Blueprint $table) {
            // Respuestas de un solo nivel, como en Facebook: responder a una
            // respuesta la cuelga del mismo comentario principal.
            $table->foreignId('parent_id')->nullable()->after('user_id')
                ->constrained('post_comments')->cascadeOnDelete();

            // Si quien comenta es el dueño en modo negocio, el comentario se
            // muestra con el nombre y la foto del negocio.
            $table->foreignId('as_business_id')->nullable()->after('parent_id')
                ->constrained('businesses')->nullOnDelete();

            $table->timestamp('edited_at')->nullable()->after('body');

            $table->index(['post_id', 'parent_id', 'created_at']);
        });

        Schema::table('plans', function (Blueprint $table) {
            // Historias por día. null = sin límite.
            $table->unsignedSmallInteger('story_quota_daily')->nullable()->after('post_quota_weekly');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('story_quota_daily');
        });

        Schema::table('post_comments', function (Blueprint $table) {
            $table->dropIndex(['post_id', 'parent_id', 'created_at']);
            $table->dropConstrainedForeignId('as_business_id');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('edited_at');
        });
    }
};
