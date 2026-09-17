<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Publicaciones e historias pueden llevar video, no solo fotos. El póster es
 * la imagen que se ve mientras el video carga (y en las miniaturas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_media', function (Blueprint $table) {
            $table->string('type', 10)->default('image')->after('post_id');
            $table->string('poster_path')->nullable()->after('webp_path');
        });

        Schema::table('stories', function (Blueprint $table) {
            $table->string('media_type', 10)->default('image')->after('media_path');
            $table->string('poster_path')->nullable()->after('media_type');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'poster_path']);
        });

        Schema::table('post_media', function (Blueprint $table) {
            $table->dropColumn(['type', 'poster_path']);
        });
    }
};
