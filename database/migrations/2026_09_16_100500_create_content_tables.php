<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();

            // Año-semana ISO. Hace trivial contar el cupo semanal sin
            // recalcular rangos de fechas en cada consulta.
            $table->string('week_key', 8)->index();

            // Un producto o servicio enlazado; es lo que pinta la tarjeta
            // "Ver en el catálogo" dentro de la publicación.
            $table->nullableMorphs('linkable');

            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('reactions_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'published_at']);
        });

        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('webp_path')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Una historia sale del feed a las 24 h, pero NO se borra: si está en
        // un destacado sigue viéndose en el perfil para siempre.
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('media_path');
            $table->string('caption')->nullable();
            $table->nullableMorphs('linkable');
            $table->timestamp('expires_at')->index();
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'expires_at']);
        });

        // Historias destacadas: colecciones permanentes en el perfil.
        Schema::create('story_highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('title', 40);
            $table->string('cover_path')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('story_highlight_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_highlight_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['story_highlight_id', 'story_id']);
        });

        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'business_id']);
        });

        Schema::create('post_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
        });

        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('post_reactions');
        Schema::dropIfExists('follows');
        Schema::dropIfExists('story_highlight_items');
        Schema::dropIfExists('story_highlights');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('post_media');
        Schema::dropIfExists('posts');
    }
};
