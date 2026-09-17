<?php

use App\Enums\ModerationState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('moderation_state')->default(ModerationState::Ok->value);

            // Tres faltas y se suspende. Se reinician al año, porque castigar
            // para siempre un error de hace dos años no sirve a nadie.
            $table->unsignedTinyInteger('strikes')->default(0);
            $table->timestamp('strikes_reset_at')->nullable();
            $table->text('moderation_note')->nullable();

            // Aceptación de los Términos: tu defensa legal. Sin la versión y la
            // fecha no puedes suspender a nadie sin exponerte.
            $table->string('policy_version')->nullable();
            $table->timestamp('policy_accepted_at')->nullable();
            $table->ipAddress('policy_accepted_ip')->nullable();

            $table->index('moderation_state');
        });

        Schema::table('categories', function (Blueprint $table) {
            // Categorías reguladas: no se publica sin comprobante aprobado.
            $table->boolean('requires_verification')->default(false);
            $table->string('required_document')->nullable();
        });

        // Lo que el escáner automático detectó, guardado tal cual para poder
        // auditar por qué se bloqueó o se marcó algo.
        Schema::create('content_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->morphs('flaggable');          // Product, Service, Post, Story
            $table->string('risk');
            $table->string('category')->nullable();
            $table->string('matched_term')->nullable();
            $table->string('required_document')->nullable();
            $table->string('state')->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['state', 'risk']);
        });

        // Comprobantes que sube el negocio para categorías reguladas.
        Schema::create('verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('kind');               // "Cédula profesional", etc.
            $table->string('path');
            $table->string('state')->default('pending');
            $table->date('expires_on')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'state']);
        });

        // Toda acción de moderación queda registrada con quién y cuándo.
        // Sin esto no hay forma de defender una suspensión ni de detectar a un
        // moderador que se está pasando.
        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->morphs('subject');
            $table->string('action');             // hide, restore, strike, limit, suspend, clear
            $table->string('reason');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Reportes: añadimos el SLA calculado para poder ordenar la cola.
        Schema::table('reports', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('state');
            $table->boolean('auto_hidden')->default(false)->after('due_at');
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['due_at', 'auto_hidden']);
        });
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('verification_documents');
        Schema::dropIfExists('content_flags');
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['requires_verification', 'required_document']);
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'moderation_state', 'strikes', 'strikes_reset_at', 'moderation_note',
                'policy_version', 'policy_accepted_at', 'policy_accepted_ip',
            ]);
        });
    }
};
