<?php

namespace Database\Seeders;

use App\Models\Curso;
use App\Models\Eixo;
use App\Models\RegraVisita;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\Usuario;
use App\Models\VisitaEvento;
use App\Models\VisitaTecnica;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SvtSeeder extends Seeder
{
    public function run(): void
    {
        $senha = Hash::make('svt2026');

        $perfis = [
            [
                'email' => 'instrutor@df.senac.br',
                'nome' => 'Instrutor de Turma',
                'perfil' => Usuario::PERFIL_INSTRUTOR,
                'unidade' => 'Asa Norte',
                'area' => 'Instrutoria',
            ],
            [
                'email' => 'nucleo@df.senac.br',
                'nome' => 'Núcleo Pedagógico Asa Norte',
                'perfil' => Usuario::PERFIL_NUCLEO,
                'unidade' => 'Asa Norte',
                'area' => 'Núcleo Pedagógico',
            ],
            [
                'email' => 'cped.tecnologia@df.senac.br',
                'nome' => 'Coordenação de Tecnologia',
                'perfil' => Usuario::PERFIL_CPED,
                'unidade' => 'DEP/CPED',
                'area' => 'Coordenação de Tecnologia',
            ],
            [
                'email' => 'cped.gastronomia@df.senac.br',
                'nome' => 'Coordenação de Gastronomia',
                'perfil' => Usuario::PERFIL_CPED,
                'unidade' => 'DEP/CPED',
                'area' => 'Coordenação de Gastronomia e Turismo',
            ],
            [
                'email' => 'cped.ambiente@df.senac.br',
                'nome' => 'Coordenação de Ambiente e Saúde',
                'perfil' => Usuario::PERFIL_CPED,
                'unidade' => 'DEP/CPED',
                'area' => 'Coordenação de Ambiente e Saúde',
            ],
            [
                'email' => 'cped.gestao@df.senac.br',
                'nome' => 'Coordenação de Gestão',
                'perfil' => Usuario::PERFIL_CPED,
                'unidade' => 'DEP/CPED',
                'area' => 'Coordenação de Gestão',
            ],
            [
                'email' => 'cped.beleza@df.senac.br',
                'nome' => 'Coordenação de Beleza',
                'perfil' => Usuario::PERFIL_CPED,
                'unidade' => 'DEP/CPED',
                'area' => 'Coordenação de Beleza',
            ],
            [
                'email' => 'direcao@df.senac.br',
                'nome' => 'Direção Pedagógica',
                'perfil' => Usuario::PERFIL_DIRECAO,
                'unidade' => 'DEP/CPED',
                'area' => 'Direção Pedagógica',
            ],
            [
                'email' => 'nulog@df.senac.br',
                'nome' => 'Núcleo de Logística',
                'perfil' => Usuario::PERFIL_NULOG,
                'unidade' => 'NULOG',
                'area' => 'Transporte',
            ],
        ];

        foreach ($perfis as $dados) {
            Usuario::updateOrCreate(
                ['email' => $dados['email']],
                [
                    'nome' => $dados['nome'],
                    'senha' => $senha,
                    'perfil' => $dados['perfil'],
                    'status' => true,
                    'unidade' => $dados['unidade'],
                    'area' => $dados['area'],
                ]
            );
        }

        $instrutor = Usuario::query()->where('email', 'instrutor@df.senac.br')->first();
        $this->prepararCatalogo($instrutor);

        $exemplos = [
            ['turma' => 'TEC-2026-A', 'local' => 'Museu Nacional', 'etapa' => 'nucleo'],
            ['turma' => 'GAS-2026-B', 'local' => 'Mercado Central', 'etapa' => 'cped'],
            ['turma' => 'AMB-2026-A', 'local' => 'Hospital de Base', 'etapa' => 'rascunho'],
            ['turma' => 'MOD-2026-A', 'local' => 'Ateliê de moda', 'etapa' => 'recusada_nucleo', 'motivo' => 'Dado fictício: visita sem relação suficiente com a unidade curricular.'],
            ['turma' => 'BEL-2026-A', 'local' => 'Salão escola', 'etapa' => 'direcao'],
            ['turma' => 'AMB-2026-A', 'local' => 'Laboratório', 'etapa' => 'nulog'],
            ['turma' => 'BEL-2026-A', 'local' => 'Estúdio', 'etapa' => 'aprovada'],
            ['turma' => 'MOD-2026-A', 'local' => 'Fábrica', 'etapa' => 'realizada'],
            ['turma' => 'GAS-2026-B', 'local' => 'Feira', 'etapa' => 'relatorio'],
        ];

        foreach ($exemplos as $exemplo) {
            $turma = Turma::query()->with('curso.eixo', 'unidade')->where('nome', $exemplo['turma'])->first();
            if (! $turma) {
                continue;
            }

            $visita = VisitaTecnica::query()->updateOrCreate(
                ['turma' => $exemplo['turma'], 'local_visita' => $exemplo['local']],
                [
                    'unidade' => $turma->unidade->nome,
                    'unidade_id' => $turma->unidade_id,
                    'turma_id' => $turma->id,
                    'instrutor' => 'Instrutor de Turma',
                    'curso' => $turma->curso->nome,
                    'curso_id' => $turma->curso_id,
                    'tipo_curso' => $turma->curso->tipo_curso,
                    'eixo' => $turma->curso->eixo->nome,
                    'eixo_id' => $turma->curso->eixo_id,
                    'data_solicitacao' => now()->toDateString(),
                    'data_visita_prevista' => now()->addDays(20)->toDateString(),
                    'horario_pretendido' => '08:30',
                    'atividade' => 'Dado fictício. Visita alinhada à unidade curricular da turma.',
                    'objetivo' => 'Dado fictício. Observar a prática profissional do eixo.',
                    'relacao_curricular' => 'Dado fictício. Conteúdo da unidade curricular em curso.',
                    'status' => config('svt.etapas.'.$exemplo['etapa']),
                    'etapa' => $exemplo['etapa'],
                    'motivo_decisao' => $exemplo['motivo'] ?? null,
                    'responsavel' => 'Instrutor de Turma',
                    'responsavel_area' => config('svt.responsaveis.'.$turma->curso->eixo->nome),
                    'acima_do_limite' => false,
                    'limite' => config('svt.tipos.'.$turma->curso->tipo_curso.'.limite'),
                    'criado_por' => $instrutor?->id,
                    'ficticio' => true,
                    'transporte_tipo' => $exemplo['etapa'] === 'aprovada' ? 'Ônibus' : null,
                    'veiculo' => $exemplo['etapa'] === 'aprovada' ? 'Micro-ônibus 12' : null,
                    'placa' => $exemplo['etapa'] === 'aprovada' ? 'ABC1D23' : null,
                    'data_transporte' => $exemplo['etapa'] === 'aprovada' ? now()->addDays(20)->toDateString() : null,
                    'horario_transporte' => $exemplo['etapa'] === 'aprovada' ? '07:30' : null,
                    'horario_retorno' => $exemplo['etapa'] === 'aprovada' ? '12:00' : null,
                    'motorista' => $exemplo['etapa'] === 'aprovada' ? 'Motorista fictício' : null,
                    'relatorio_texto' => $exemplo['etapa'] === 'relatorio' ? 'Dado fictício. A turma observou a produção e registrou os aprendizados.' : null,
                    'realizada_em' => in_array($exemplo['etapa'], ['realizada', 'relatorio'], true) ? now() : null,
                    'relatorio_entregue_em' => $exemplo['etapa'] === 'relatorio' ? now() : null,
                ]
            );

            VisitaEvento::query()->firstOrCreate(
                ['visita_tecnica_id' => $visita->id, 'evento' => 'exemplo'],
                [
                    'usuario_id' => $instrutor?->id,
                    'perfil' => 'Instrutor',
                    'etapa_nova' => $visita->etapa,
                    'texto' => 'Dado fictício de demonstração. Não é um registro real de produção.',
                ]
            );
        }
    }

    private function prepararCatalogo(?Usuario $instrutor): void
    {
        foreach (array_keys(config('svt.responsaveis')) as $nome) {
            Eixo::query()->updateOrCreate(['nome' => $nome], ['ativo' => true]);
        }

        foreach (config('svt.unidades') as $nome) {
            Unidade::query()->updateOrCreate(['nome' => $nome], ['ativo' => true]);
        }

        $asaNorte = Unidade::query()->where('nome', 'Asa Norte')->first();
        $cursos = [
            ['eixo' => 'Gastronomia e Turismo', 'nome' => 'Cozinha profissional', 'tipo' => 'qualificacao', 'ch' => 160, 'turma' => 'GAS-2026-B'],
            ['eixo' => 'Ambiente e Saúde', 'nome' => 'Técnico em Enfermagem', 'tipo' => 'tecnico', 'ch' => 1200, 'turma' => 'AMB-2026-A'],
            ['eixo' => 'Gestão e Moda', 'nome' => 'Gestão de Pessoas', 'tipo' => 'aperfeicoamento', 'ch' => 40, 'turma' => 'MOD-2026-A'],
            ['eixo' => 'Tecnologia e Economia Criativa', 'nome' => 'Técnico em Informática', 'tipo' => 'tecnico', 'ch' => 1200, 'turma' => 'TEC-2026-A'],
            ['eixo' => 'Beleza e Cuidado Pessoal', 'nome' => 'Cabeleireiro', 'tipo' => 'aprendizagem', 'ch' => 800, 'turma' => 'BEL-2026-A'],
        ];

        foreach ($cursos as $item) {
            $eixo = Eixo::query()->where('nome', $item['eixo'])->first();
            $curso = Curso::query()->updateOrCreate(
                ['nome' => $item['nome']],
                [
                    'eixo_id' => $eixo->id,
                    'tipo_curso' => $item['tipo'],
                    'carga_horaria' => $item['ch'],
                    'ativo' => true,
                ]
            );
            Turma::query()->updateOrCreate(
                ['nome' => $item['turma']],
                [
                    'curso_id' => $curso->id,
                    'unidade_id' => $asaNorte->id,
                    'instrutor_id' => $instrutor?->id,
                    'ativo' => true,
                ]
            );
        }

        $observacao = 'Dado fictício e regra provisória da ata de 30/09/2026. O cliente ainda não confirmou período, carga horária nem o que consome a cota.';
        foreach (config('svt.tipos') as $tipo => $info) {
            RegraVisita::query()->updateOrCreate(
                ['tipo_curso' => $tipo, 'curso_id' => null],
                [
                    'limite' => $info['limite'],
                    'vigencia_inicio' => '2026-01-01',
                    'vigencia_fim' => null,
                    'criterio' => 'realizadas_e_programadas',
                    'ativo' => true,
                    'observacao' => $observacao,
                ]
            );
        }

        foreach (config('svt.responsaveis') as $nome => $area) {
            $eixo = Eixo::query()->where('nome', $nome)->first();
            $coordenador = Usuario::query()
                ->where('perfil', Usuario::PERFIL_CPED)
                ->where('area', $area)
                ->first();
            if ($eixo && $coordenador) {
                $coordenador->eixos()->syncWithoutDetaching([$eixo->id]);
            }
        }
    }
}
