<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('eixos')) {
            Schema::create('eixos', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 120)->unique();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('unidades')) {
            Schema::create('unidades', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 100)->unique();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cursos')) {
            Schema::create('cursos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('eixo_id')->constrained('eixos')->restrictOnDelete();
                $table->string('nome', 180);
                $table->string('tipo_curso', 40);
                $table->unsignedSmallInteger('carga_horaria')->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
                $table->index(['eixo_id', 'ativo']);
            });
        }

        if (! Schema::hasTable('turmas')) {
            Schema::create('turmas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
                $table->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
                $table->foreignId('instrutor_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->string('nome', 120);
                $table->boolean('ativo')->default(true);
                $table->timestamps();
                $table->index(['instrutor_id', 'ativo']);
            });
        }

        if (! Schema::hasTable('regras_visita')) {
            Schema::create('regras_visita', function (Blueprint $table) {
                $table->id();
                $table->string('tipo_curso', 40)->nullable();
                $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();
                $table->unsignedSmallInteger('carga_horaria_min')->nullable();
                $table->unsignedSmallInteger('carga_horaria_max')->nullable();
                $table->unsignedTinyInteger('limite');
                $table->date('vigencia_inicio');
                $table->date('vigencia_fim')->nullable();
                $table->string('criterio', 40)->default('realizadas_e_programadas');
                $table->boolean('ativo')->default(true);
                $table->string('observacao', 500)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('usuario_eixos')) {
            Schema::create('usuario_eixos', function (Blueprint $table) {
                $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
                $table->foreignId('eixo_id')->constrained('eixos')->cascadeOnDelete();
                $table->primary(['usuario_id', 'eixo_id']);
            });
        }

        if (! Schema::hasTable('visita_eventos')) {
            Schema::create('visita_eventos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('visita_tecnica_id')->constrained('visita_tecnicas')->cascadeOnDelete();
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->string('perfil', 40)->nullable();
                $table->string('evento', 40);
                $table->string('etapa_anterior', 30)->nullable();
                $table->string('etapa_nova', 30)->nullable();
                $table->text('texto')->nullable();
                $table->timestamps();
                $table->index(['visita_tecnica_id', 'id']);
            });
        }

        if (! Schema::hasTable('visita_tecnicas')) {
            return;
        }

        Schema::table('visita_tecnicas', function (Blueprint $table) {
            $colunas = [
                'horario_pretendido' => fn () => $table->string('horario_pretendido', 8)->nullable(),
                'objetivo' => fn () => $table->text('objetivo')->nullable(),
                'relacao_curricular' => fn () => $table->text('relacao_curricular')->nullable(),
                'horario_retorno' => fn () => $table->string('horario_retorno', 8)->nullable(),
                'info_transporte' => fn () => $table->text('info_transporte')->nullable(),
                'relatorio_texto' => fn () => $table->text('relatorio_texto')->nullable(),
                'realizada_em' => fn () => $table->timestamp('realizada_em')->nullable(),
                'relatorio_entregue_em' => fn () => $table->timestamp('relatorio_entregue_em')->nullable(),
                'ficticio' => fn () => $table->boolean('ficticio')->default(false),
                'curso_id' => fn () => $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete(),
                'turma_id' => fn () => $table->foreignId('turma_id')->nullable()->constrained('turmas')->nullOnDelete(),
                'eixo_id' => fn () => $table->foreignId('eixo_id')->nullable()->constrained('eixos')->nullOnDelete(),
                'unidade_id' => fn () => $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete(),
            ];

            foreach ($colunas as $nome => $adicionar) {
                if (! Schema::hasColumn('visita_tecnicas', $nome)) {
                    $adicionar();
                }
            }
        });

        Schema::table('visita_tecnicas', function (Blueprint $table) {
            $table->index(['etapa', 'unidade'], 'visita_etapa_unidade_idx');
            $table->index(['etapa', 'responsavel_area'], 'visita_etapa_area_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('visita_tecnicas')) {
            Schema::table('visita_tecnicas', function (Blueprint $table) {
                foreach (['visita_etapa_unidade_idx', 'visita_etapa_area_idx'] as $indice) {
                    try {
                        $table->dropIndex($indice);
                    } catch (\Throwable) {
                        //
                    }
                }
            });
        }

        Schema::dropIfExists('visita_eventos');
        Schema::dropIfExists('usuario_eixos');

        if (Schema::hasTable('visita_tecnicas')) {
            Schema::table('visita_tecnicas', function (Blueprint $table) {
                foreach (['curso_id', 'turma_id', 'eixo_id', 'unidade_id'] as $fk) {
                    if (Schema::hasColumn('visita_tecnicas', $fk)) {
                        $table->dropConstrainedForeignId($fk);
                    }
                }

                $simples = [
                    'horario_pretendido', 'objetivo', 'relacao_curricular', 'horario_retorno',
                    'info_transporte', 'relatorio_texto', 'realizada_em', 'relatorio_entregue_em', 'ficticio',
                ];
                $existentes = array_values(array_filter(
                    $simples,
                    fn (string $coluna) => Schema::hasColumn('visita_tecnicas', $coluna)
                ));
                if ($existentes !== []) {
                    $table->dropColumn($existentes);
                }
            });
        }

        Schema::dropIfExists('regras_visita');
        Schema::dropIfExists('turmas');
        Schema::dropIfExists('cursos');
        Schema::dropIfExists('unidades');
        Schema::dropIfExists('eixos');
    }
};
