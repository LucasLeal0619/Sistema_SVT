<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visita_tecnicas')) {
            return;
        }

        Schema::table('visita_tecnicas', function (Blueprint $table) {
            $colunas = [
                'turma' => fn () => $table->string('turma', 120)->nullable(),
                'instrutor' => fn () => $table->string('instrutor', 150)->nullable(),
                'curso' => fn () => $table->string('curso', 180)->nullable(),
                'tipo_curso' => fn () => $table->string('tipo_curso', 40)->nullable(),
                'local_visita' => fn () => $table->string('local_visita', 180)->nullable(),
                'atividade' => fn () => $table->text('atividade')->nullable(),
                'justificativa' => fn () => $table->text('justificativa')->nullable(),
                'etapa' => fn () => $table->string('etapa', 30)->nullable()->index(),
                'motivo_decisao' => fn () => $table->text('motivo_decisao')->nullable(),
                'responsavel_area' => fn () => $table->string('responsavel_area', 150)->nullable(),
                'acima_do_limite' => fn () => $table->boolean('acima_do_limite')->default(false),
                'visitas_utilizadas' => fn () => $table->unsignedTinyInteger('visitas_utilizadas')->nullable(),
                'limite' => fn () => $table->unsignedTinyInteger('limite')->nullable(),
                'transporte_tipo' => fn () => $table->string('transporte_tipo', 40)->nullable(),
                'veiculo' => fn () => $table->string('veiculo', 80)->nullable(),
                'placa' => fn () => $table->string('placa', 10)->nullable(),
                'data_transporte' => fn () => $table->date('data_transporte')->nullable(),
                'horario_transporte' => fn () => $table->string('horario_transporte', 8)->nullable(),
                'motorista' => fn () => $table->string('motorista', 150)->nullable(),
                'relatorio_arquivo' => fn () => $table->string('relatorio_arquivo', 255)->nullable(),
            ];

            foreach ($colunas as $nome => $adicionar) {
                if (! Schema::hasColumn('visita_tecnicas', $nome)) {
                    $adicionar();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('visita_tecnicas')) {
            return;
        }

        Schema::table('visita_tecnicas', function (Blueprint $table) {
            $nomes = [
                'turma', 'instrutor', 'curso', 'tipo_curso', 'local_visita', 'atividade',
                'justificativa', 'etapa', 'motivo_decisao', 'responsavel_area', 'acima_do_limite',
                'visitas_utilizadas', 'limite', 'transporte_tipo', 'veiculo', 'placa',
                'data_transporte', 'horario_transporte', 'motorista', 'relatorio_arquivo',
            ];

            $existentes = array_values(array_filter(
                $nomes,
                fn (string $coluna) => Schema::hasColumn('visita_tecnicas', $coluna)
            ));

            if ($existentes !== []) {
                $table->dropColumn($existentes);
            }
        });
    }
};
