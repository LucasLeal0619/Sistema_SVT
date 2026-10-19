<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tabelas = [
        'visita_tecnicas',
    ];

    public function up(): void
    {
        foreach ($this->tabelas as $tabela) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                if (! Schema::hasColumn($tabela, 'criado_por')) {
                    $table->foreignId('criado_por')
                        ->nullable()
                        ->constrained('usuarios')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn($tabela, 'atualizado_por')) {
                    $table->foreignId('atualizado_por')
                        ->nullable()
                        ->constrained('usuarios')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('fluxogramas') && ! Schema::hasColumn('fluxogramas', 'atualizado_por')) {
            Schema::table('fluxogramas', function (Blueprint $table) {
                $table->foreignId('atualizado_por')
                    ->nullable()
                    ->constrained('usuarios')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('kanban_cartoes') && ! Schema::hasColumn('kanban_cartoes', 'atualizado_por')) {
            Schema::table('kanban_cartoes', function (Blueprint $table) {
                $table->foreignId('atualizado_por')
                    ->nullable()
                    ->constrained('usuarios')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tabelas as $tabela) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                if (Schema::hasColumn($tabela, 'criado_por')) {
                    $table->dropConstrainedForeignId('criado_por');
                }
                if (Schema::hasColumn($tabela, 'atualizado_por')) {
                    $table->dropConstrainedForeignId('atualizado_por');
                }
            });
        }

        if (Schema::hasTable('fluxogramas') && Schema::hasColumn('fluxogramas', 'atualizado_por')) {
            Schema::table('fluxogramas', function (Blueprint $table) {
                $table->dropConstrainedForeignId('atualizado_por');
            });
        }

        if (Schema::hasTable('kanban_cartoes') && Schema::hasColumn('kanban_cartoes', 'atualizado_por')) {
            Schema::table('kanban_cartoes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('atualizado_por');
            });
        }
    }
};
