<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auditoria_eventos')) {
            return;
        }

        Schema::create('auditoria_eventos', function (Blueprint $table) {
            $table->id();
            $table->timestamp('ocorrido_em');
            $table->string('fuso', 40);
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('usuario_nome', 180)->nullable();
            $table->string('usuario_email', 180)->nullable();
            $table->string('perfil', 40)->nullable();
            $table->string('categoria', 40);
            $table->string('acao', 60);
            $table->string('entidade_tipo', 60)->nullable();
            $table->string('entidade_id', 40)->nullable();
            $table->string('unidade', 120)->nullable();
            $table->string('eixo', 120)->nullable();
            $table->string('resultado', 20);
            $table->json('anterior')->nullable();
            $table->json('novo')->nullable();
            $table->string('correlacao', 40);
            $table->string('ip', 45)->nullable();
            $table->string('agente', 160)->nullable();
            $table->char('hash_anterior', 64);
            $table->char('hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->index('ocorrido_em');
            $table->index(['categoria', 'acao']);
            $table->index(['perfil', 'resultado']);
            $table->index(['entidade_tipo', 'entidade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_eventos');
    }
};
