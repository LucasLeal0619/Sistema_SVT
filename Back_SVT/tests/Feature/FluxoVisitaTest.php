<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Eixo;
use App\Models\RegraVisita;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\Usuario;
use App\Models\VisitaTecnica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FluxoVisitaTest extends TestCase
{
    use RefreshDatabase;

    private Unidade $unidade;

    private Usuario $instrutor;

    private Usuario $outroInstrutor;

    private Usuario $nucleo;

    private Usuario $outroNucleo;

    private Usuario $direcao;

    private Usuario $nulog;

    /** @var array<string, Usuario> */
    private array $coordenadores = [];

    /** @var array<string, Turma> */
    private array $turmas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->unidade = Unidade::query()->create(['nome' => 'Asa Norte', 'ativo' => true]);
        $outra = Unidade::query()->create(['nome' => 'Taguatinga', 'ativo' => true]);

        $this->instrutor = $this->usuario('Instrutor', 'instrutor@df.senac.br', 'Asa Norte');
        $this->outroInstrutor = $this->usuario('Instrutor', 'outro@df.senac.br', 'Asa Norte');
        $this->nucleo = $this->usuario('Núcleo Pedagógico', 'nucleo@df.senac.br', 'Asa Norte');
        $this->outroNucleo = $this->usuario('Núcleo Pedagógico', 'nucleo.tag@df.senac.br', 'Taguatinga');
        $this->direcao = $this->usuario('Direção Pedagógica', 'direcao@df.senac.br', 'DEP/CPED');
        $this->nulog = $this->usuario('NULOG', 'nulog@df.senac.br', 'NULOG');

        foreach (config('svt.responsaveis') as $nome => $area) {
            $eixo = Eixo::query()->create(['nome' => $nome, 'ativo' => true]);
            $curso = Curso::query()->create([
                'eixo_id' => $eixo->id,
                'nome' => 'Curso '.$nome,
                'tipo_curso' => 'tecnico',
                'carga_horaria' => 1200,
                'ativo' => true,
            ]);
            $this->turmas[$nome] = Turma::query()->create([
                'curso_id' => $curso->id,
                'unidade_id' => $nome === 'Gestão e Moda' ? $outra->id : $this->unidade->id,
                'instrutor_id' => $this->instrutor->id,
                'nome' => 'TURMA '.$nome,
                'ativo' => true,
            ]);
            $coordenador = $this->usuario('Coordenação CPED', 'cped.'.md5($nome).'@df.senac.br', 'DEP/CPED', $area);
            $coordenador->eixos()->sync([$eixo->id]);
            $this->coordenadores[$nome] = $coordenador;
        }

        RegraVisita::query()->create([
            'tipo_curso' => 'tecnico',
            'limite' => 1,
            'vigencia_inicio' => '2026-01-01',
            'criterio' => 'realizadas_e_programadas',
            'ativo' => true,
            'observacao' => 'Regra provisória de teste.',
        ]);
    }

    public function test_os_cinco_eixos_existem_e_cada_coordenador_so_ve_o_seu(): void
    {
        $this->assertCount(5, Eixo::query()->pluck('nome'));

        foreach ($this->turmas as $nome => $turma) {
            $this->actingAs($this->instrutor, 'sanctum')
                ->postJson('/api/svt/solicitacoes', $this->payload($turma))
                ->assertCreated();
        }

        $tecnologia = $this->coordenadores['Tecnologia e Economia Criativa'];
        $this->actingAs($this->nucleo, 'sanctum');
        foreach (VisitaTecnica::query()->where('etapa', 'nucleo')->where('unidade', 'Asa Norte')->get() as $visita) {
            $this->postJson("/api/svt/solicitacoes/{$visita->id}/decisao", ['aprovar' => true])->assertOk();
        }

        $fila = $this->actingAs($tecnologia, 'sanctum')
            ->getJson('/api/svt/solicitacoes?fila=1')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($fila);
        foreach ($fila as $item) {
            $this->assertSame('Tecnologia e Economia Criativa', $item['eixo']);
        }

        $alheia = VisitaTecnica::query()->where('eixo', 'Gastronomia e Turismo')->where('etapa', 'cped')->first();
        $this->assertNotNull($alheia);
        $this->postJson("/api/svt/solicitacoes/{$alheia->id}/decisao", ['aprovar' => true])
            ->assertStatus(422);
        $this->getJson("/api/svt/solicitacoes/{$alheia->id}")->assertNotFound();
    }

    public function test_fluxo_completo_de_um_eixo_com_historico_transporte_e_relatorio(): void
    {
        $turma = $this->turmas['Beleza e Cuidado Pessoal'];
        $id = $this->actingAs($this->instrutor, 'sanctum')
            ->postJson('/api/svt/solicitacoes', $this->payload($turma, ['enviar' => false, 'local_visita' => null]))
            ->assertCreated()
            ->json('visita.id');

        $this->putJson("/api/svt/solicitacoes/{$id}", $this->payload($turma, ['enviar' => true]))
            ->assertOk()
            ->assertJsonPath('visita.etapa', 'nucleo')
            ->assertJsonPath('visita.status', 'Aguardando Núcleo Pedagógico');

        $this->actingAs($this->outroNucleo, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])
            ->assertStatus(422);

        $this->actingAs($this->nucleo, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])
            ->assertOk()
            ->assertJsonPath('visita.etapa', 'cped');

        $this->actingAs($this->coordenadores['Beleza e Cuidado Pessoal'], 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])
            ->assertOk()
            ->assertJsonPath('visita.etapa', 'direcao');

        $this->actingAs($this->direcao, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])
            ->assertOk()
            ->assertJsonPath('visita.etapa', 'nulog')
            ->assertJsonPath('visita.status', 'Aguardando Logística');

        $this->actingAs($this->nulog, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/transporte", [
                'transporte_tipo' => 'Ônibus',
                'veiculo' => 'Micro 1',
                'placa' => 'ABC1D23',
                'data_transporte' => now()->addDay()->toDateString(),
                'horario_transporte' => '07:30',
                'horario_retorno' => '12:00',
                'motorista' => 'Ana',
            ])->assertOk()
            ->assertJsonPath('visita.etapa', 'aprovada')
            ->assertJsonPath('visita.status', 'Transporte definido');

        $this->actingAs($this->instrutor, 'sanctum')
            ->getJson("/api/svt/solicitacoes/{$id}")
            ->assertOk()
            ->assertJsonPath('visita.motorista', 'Ana');

        $this->postJson("/api/svt/solicitacoes/{$id}/realizar")->assertOk()
            ->assertJsonPath('visita.etapa', 'realizada')
            ->assertJsonPath('visita.status', 'Realizada');

        Storage::fake('local');
        Storage::fake('public');
        $this->post('/api/svt/solicitacoes/'.$id.'/relatorio', [
            'arquivo' => UploadedFile::fake()->createWithContent('relatorio.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n"),
            'relatorio_texto' => 'A turma observou o atendimento.',
        ])->assertOk()
            ->assertJsonPath('visita.etapa', 'relatorio')
            ->assertJsonPath('visita.status', 'Relatório entregue')
            ->assertJsonPath('visita.tem_relatorio', true)
            ->assertJsonMissingPath('visita.relatorio_url');

        $eventos = $this->getJson("/api/svt/solicitacoes/{$id}")->assertOk()->json('eventos');
        $this->assertGreaterThanOrEqual(6, count($eventos));
    }

    public function test_reprovacao_do_nucleo_nao_chega_na_coordenacao(): void
    {
        $id = $this->criarEnviada($this->turmas['Ambiente e Saúde']);

        $this->actingAs($this->nucleo, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => false])
            ->assertStatus(422);

        $this->postJson("/api/svt/solicitacoes/{$id}/decisao", [
            'aprovar' => false,
            'motivo' => 'Sem relação com a unidade curricular.',
        ])->assertOk()->assertJsonPath('visita.etapa', 'recusada_nucleo');

        $this->actingAs($this->coordenadores['Ambiente e Saúde'], 'sanctum')
            ->getJson('/api/svt/solicitacoes?fila=1')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_instrutor_nao_solicita_turma_de_outro_e_cota_extraordinaria_exige_justificativa(): void
    {
        $turma = $this->turmas['Tecnologia e Economia Criativa'];

        $this->actingAs($this->outroInstrutor, 'sanctum')
            ->postJson('/api/svt/solicitacoes', $this->payload($turma))
            ->assertStatus(422);

        $this->actingAs($this->instrutor, 'sanctum');
        $id = $this->criarEnviada($turma);
        $this->aprovarAteTransporte($id);

        $this->actingAs($this->instrutor, 'sanctum')
            ->postJson('/api/svt/solicitacoes', $this->payload($turma, ['local_visita' => 'Segunda saída']))
            ->assertStatus(422);

        $this->postJson('/api/svt/solicitacoes', $this->payload($turma, [
            'local_visita' => 'Segunda saída',
            'justificativa' => 'Atividade extra prevista no plano e ainda não realizada.',
        ]))->assertCreated()->assertJsonPath('visita.acima_do_limite', true);
    }

    public function test_decisao_duplicada_nao_avanca_de_novo(): void
    {
        $id = $this->criarEnviada($this->turmas['Gastronomia e Turismo']);
        $this->actingAs($this->nucleo, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])
            ->assertOk();

        $this->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])
            ->assertStatus(422);

        $this->assertSame('cped', VisitaTecnica::query()->find($id)->etapa);
    }

    public function test_crud_de_eixo_desativa_sem_apagar_e_painel_do_instrutor_nao_varre_a_equipe(): void
    {
        $admin = $this->usuario('Administrador', 'admin@df.senac.br', 'Asa Norte');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/svt/cadastros/eixos', ['nome' => 'Eixo temporário'])
            ->assertCreated();

        $eixo = Eixo::query()->where('nome', 'Eixo temporário')->first();
        $this->postJson("/api/svt/cadastros/eixos/{$eixo->id}/desativar")->assertOk();
        $this->assertFalse($eixo->fresh()->ativo);
        $this->assertNotNull(Eixo::query()->find($eixo->id));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->instrutor, 'sanctum')->getJson('/api/svt/painel')->assertOk();
        $this->assertLessThan(15, count(DB::getQueryLog()));
    }

    public function test_relatorio_aceita_word_e_pdf_e_so_baixa_com_autorizacao(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $id = $this->criarEnviada($this->turmas['Ambiente e Saúde']);
        $this->aprovarAteTransporte($id);
        $this->actingAs($this->instrutor, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/realizar")
            ->assertOk();

        $this->post('/api/svt/solicitacoes/'.$id.'/relatorio', [
            'arquivo' => UploadedFile::fake()->createWithContent('nota.txt', "apenas texto\n"),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->post('/api/svt/solicitacoes/'.$id.'/relatorio', [
            'arquivo' => UploadedFile::fake()->createWithContent('relatorio.pdf.exe', "%PDF-1.4\n%%EOF\n"),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $doc = hex2bin('D0CF11E0A1B11AE1').str_repeat("\0", 64);
        $this->post('/api/svt/solicitacoes/'.$id.'/relatorio', [
            'arquivo' => UploadedFile::fake()->createWithContent('relatorio.doc', $doc),
            'relatorio_texto' => 'Registro em Word.',
        ])->assertOk()->assertJsonPath('visita.etapa', 'relatorio');

        $caminho = VisitaTecnica::query()->findOrFail($id)->relatorio_arquivo;
        Storage::disk('local')->assertExists($caminho);
        Storage::disk('public')->assertMissing($caminho);

        $docx = $this->docxTemporario();
        $this->post('/api/svt/solicitacoes/'.$id.'/relatorio', [
            'arquivo' => new UploadedFile($docx, 'relatorio.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true),
        ])->assertOk();

        $novo = VisitaTecnica::query()->findOrFail($id)->relatorio_arquivo;
        $this->assertNotSame($caminho, $novo);
        Storage::disk('local')->assertMissing($caminho);
        Storage::disk('local')->assertExists($novo);

        $this->get('/api/svt/solicitacoes/'.$id.'/relatorio/arquivo')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->actingAs($this->coordenadores['Beleza e Cuidado Pessoal'], 'sanctum')
            ->getJson('/api/svt/solicitacoes/'.$id.'/relatorio/arquivo')
            ->assertNotFound();

        auth()->forgetGuards();
        $this->getJson('/api/svt/solicitacoes/'.$id.'/relatorio/arquivo')->assertUnauthorized();
        $this->get('/api/svt/solicitacoes/'.$id.'/relatorio/arquivo')->assertUnauthorized();
        $this->get('/api/svt/solicitacoes')->assertUnauthorized();
    }

    public function test_faixa_de_carga_horaria_prevalece_sobre_a_regra_geral_do_tipo(): void
    {
        $curso = Curso::query()->create([
            'eixo_id' => Eixo::query()->where('nome', 'Ambiente e Saúde')->first()->id,
            'nome' => 'Curso faixa curta',
            'tipo_curso' => 'tecnico',
            'carga_horaria' => 80,
            'ativo' => true,
        ]);
        $turma = Turma::query()->create([
            'curso_id' => $curso->id,
            'unidade_id' => $this->unidade->id,
            'instrutor_id' => $this->instrutor->id,
            'nome' => 'TURMA FAIXA',
            'ativo' => true,
        ]);

        RegraVisita::query()->create([
            'tipo_curso' => 'tecnico',
            'carga_horaria_min' => 40,
            'carga_horaria_max' => 200,
            'limite' => 9,
            'vigencia_inicio' => '2026-01-01',
            'criterio' => 'realizadas_e_programadas',
            'ativo' => true,
            'observacao' => 'Faixa de teste. Não é regra institucional.',
        ]);
        RegraVisita::query()->create([
            'tipo_curso' => 'tecnico',
            'carga_horaria_min' => 70,
            'carga_horaria_max' => 90,
            'limite' => 3,
            'vigencia_inicio' => '2026-01-01',
            'criterio' => 'realizadas_e_programadas',
            'ativo' => true,
            'observacao' => 'Faixa estreita de teste.',
        ]);

        $this->actingAs($this->instrutor, 'sanctum')
            ->getJson('/api/svt/cota?turma_id='.$turma->id)
            ->assertOk()
            ->assertJsonPath('limite', 3);

        $curso->update(['carga_horaria' => 1200]);
        $this->getJson('/api/svt/cota?turma_id='.$turma->id)
            ->assertOk()
            ->assertJsonPath('limite', 1);

        RegraVisita::query()->create([
            'tipo_curso' => 'tecnico',
            'curso_id' => $curso->id,
            'limite' => 6,
            'vigencia_inicio' => '2026-01-01',
            'criterio' => 'realizadas_e_programadas',
            'ativo' => true,
        ]);
        $this->getJson('/api/svt/cota?turma_id='.$turma->id)
            ->assertOk()
            ->assertJsonPath('limite', 6);
    }

    public function test_instrutor_so_ve_visita_de_outro_pelo_nome_quando_nao_ha_turma(): void
    {
        $id = $this->criarEnviada($this->turmas['Ambiente e Saúde']);
        VisitaTecnica::query()->whereKey($id)->update([
            'instrutor' => $this->outroInstrutor->nome,
        ]);

        $listaAlheia = $this->actingAs($this->outroInstrutor, 'sanctum')
            ->getJson('/api/svt/solicitacoes')
            ->assertOk()
            ->json('data');
        $this->assertFalse(collect($listaAlheia)->contains(fn ($item) => $item['id'] === $id));
        $this->getJson("/api/svt/solicitacoes/{$id}")->assertNotFound();

        $legado = VisitaTecnica::query()->create([
            'etapa' => 'nucleo',
            'status' => 'nucleo',
            'turma' => 'Turma legada',
            'instrutor' => mb_strtoupper($this->outroInstrutor->nome),
            'unidade' => 'Asa Norte',
            'curso' => 'Curso legado',
            'eixo' => 'Ambiente e Saúde',
            'criado_por' => $this->instrutor->id,
        ]);

        $listaLegada = $this->actingAs($this->outroInstrutor, 'sanctum')
            ->getJson('/api/svt/solicitacoes')
            ->assertOk()
            ->json('data');
        $this->assertTrue(collect($listaLegada)->contains(fn ($item) => $item['id'] === $legado->id));
        $this->getJson("/api/svt/solicitacoes/{$legado->id}")->assertOk();
    }

    public function test_busca_de_visita_trata_curinga_como_texto(): void
    {
        $this->criarEnviada($this->turmas['Ambiente e Saúde']);

        $this->actingAs($this->direcao, 'sanctum')
            ->getJson('/api/svt/solicitacoes?busca='.rawurlencode('%'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_relatorio_respeita_filtro_perfil_e_nome_completo_do_eixo(): void
    {
        $this->criarEnviada($this->turmas['Tecnologia e Economia Criativa']);
        $this->criarEnviada($this->turmas['Gestão e Moda']);
        $this->criarEnviada($this->turmas['Gastronomia e Turismo']);
        VisitaTecnica::query()->create([
            'etapa' => 'nucleo',
            'status' => 'nucleo',
            'eixo' => 'Gestor avulso',
            'unidade' => 'Asa Norte',
            'turma' => 'Avulsa',
            'curso' => 'Avulso',
            'criado_por' => $this->instrutor->id,
            'instrutor' => $this->instrutor->nome,
            'responsavel_area' => 'Coordenação de Gestão',
        ]);

        $geral = $this->actingAs($this->direcao, 'sanctum')
            ->getJson('/api/svt/relatorios')
            ->assertOk();
        $geral->assertJsonPath('total', 4);
        $geral->assertJsonPath('tecnologia', 1);
        $geral->assertJsonPath('gestao', 1);

        $this->getJson('/api/svt/relatorios?eixo=Tecnologia e Economia Criativa')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('tecnologia', 1)
            ->assertJsonPath('gestao', 0);

        $this->getJson('/api/svt/relatorios?etapa=relatorio')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('tecnologia', 0);

        $coordenacao = $this->actingAs($this->coordenadores['Tecnologia e Economia Criativa'], 'sanctum')
            ->getJson('/api/svt/relatorios')
            ->assertOk();
        $coordenacao->assertJsonPath('total', 1);
        $coordenacao->assertJsonPath('tecnologia', 1);
        $coordenacao->assertJsonPath('gestao', 0);

        $this->actingAs($this->outroInstrutor, 'sanctum')
            ->getJson('/api/svt/relatorios')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_cada_eixo_chega_somente_ao_coordenador_responsavel_e_volta_ao_instrutor(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        foreach ($this->turmas as $nome => $turma) {
            $id = $this->criarEnviada($turma);
            $visita = VisitaTecnica::query()->findOrFail($id);
            $this->assertSame(config('svt.responsaveis.'.$nome), $visita->responsavel_area);

            $alheio = collect($this->coordenadores)->first(fn (Usuario $usuario, string $eixo) => $eixo !== $nome);
            $this->actingAs($alheio, 'sanctum')
                ->getJson("/api/svt/solicitacoes/{$id}")
                ->assertNotFound();
            $this->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])
                ->assertStatus(422);

            $nucleo = $nome === 'Gestão e Moda' ? $this->outroNucleo : $this->nucleo;
            $this->actingAs($nucleo, 'sanctum')
                ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])
                ->assertOk()
                ->assertJsonPath('visita.etapa', 'cped');

            $this->actingAs($this->coordenadores[$nome], 'sanctum')
                ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])
                ->assertOk()
                ->assertJsonPath('visita.etapa', 'direcao');

            $this->actingAs($this->direcao, 'sanctum')
                ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])
                ->assertOk()
                ->assertJsonPath('visita.etapa', 'nulog');

            $this->actingAs($this->nulog, 'sanctum')
                ->postJson("/api/svt/solicitacoes/{$id}/transporte", [
                    'transporte_tipo' => 'Ônibus',
                    'veiculo' => 'Ônibus '.$nome,
                    'placa' => 'E2E1A23',
                    'data_transporte' => now()->addDays(3)->toDateString(),
                    'horario_transporte' => '08:00',
                    'horario_retorno' => '12:30',
                    'motorista' => 'Motorista '.$nome,
                ])->assertOk()
                ->assertJsonPath('visita.etapa', 'aprovada')
                ->assertJsonPath('visita.status', 'Transporte definido');

            $consulta = $this->actingAs($this->instrutor, 'sanctum')
                ->getJson("/api/svt/solicitacoes/{$id}")
                ->assertOk()
                ->assertJsonPath('visita.motorista', 'Motorista '.$nome)
                ->assertJsonPath('visita.placa', 'E2E1A23');

            $this->postJson("/api/svt/solicitacoes/{$id}/realizar")
                ->assertOk()
                ->assertJsonPath('visita.etapa', 'realizada');

            $this->post('/api/svt/solicitacoes/'.$id.'/relatorio', [
                'arquivo' => UploadedFile::fake()->createWithContent('relatorio.pdf', "%PDF-1.4\n%%EOF\n"),
                'relatorio_texto' => 'Aprendizado da turma de '.$nome.'.',
            ])->assertOk()->assertJsonPath('visita.etapa', 'relatorio');

            $eventos = collect($consulta->json('eventos'))->pluck('evento');
            $this->assertTrue($eventos->contains('envio'));

            $finais = collect($this->getJson("/api/svt/solicitacoes/{$id}")->assertOk()->json('eventos'))->pluck('evento');
            foreach (['envio', 'aprovacao', 'transporte_definido', 'realizacao', 'relatorio'] as $evento) {
                $this->assertTrue($finais->contains($evento), $nome.' sem evento '.$evento);
            }
            $this->assertSame(3, $finais->filter(fn ($evento) => $evento === 'aprovacao')->count(), $nome);

            $this->actingAs($this->direcao, 'sanctum')
                ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])
                ->assertStatus(422);
            $this->actingAs($this->nulog, 'sanctum')
                ->postJson("/api/svt/solicitacoes/{$id}/transporte", [
                    'transporte_tipo' => 'Van',
                    'veiculo' => 'Van extra',
                    'placa' => 'ZZZ1A23',
                    'data_transporte' => now()->addDays(4)->toDateString(),
                    'horario_transporte' => '09:00',
                    'motorista' => 'Outro',
                ])->assertStatus(422);
            $this->assertSame('relatorio', VisitaTecnica::query()->find($id)->etapa);
        }
    }

    public function test_recusa_da_coordenacao_e_da_direcao_exige_motivo_e_nao_avanca(): void
    {
        $cped = $this->criarEnviada($this->turmas['Gastronomia e Turismo']);
        $this->actingAs($this->nucleo, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$cped}/decisao", ['aprovar' => true])
            ->assertOk();

        $this->actingAs($this->coordenadores['Gastronomia e Turismo'], 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$cped}/decisao", ['aprovar' => false])
            ->assertStatus(422);
        $this->postJson("/api/svt/solicitacoes/{$cped}/decisao", [
            'aprovar' => false,
            'motivo' => 'A visita não conversa com a unidade curricular.',
        ])->assertOk()->assertJsonPath('visita.etapa', 'recusada_cped');

        $this->actingAs($this->direcao, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$cped}/decisao", ['aprovar' => true, 'motivo' => 'Tarde demais'])
            ->assertStatus(422);
        $this->assertSame('recusada_cped', VisitaTecnica::query()->find($cped)->etapa);
        $this->assertSame(
            'A visita não conversa com a unidade curricular.',
            VisitaTecnica::query()->find($cped)->motivo_decisao
        );

        $direcao = $this->criarEnviada($this->turmas['Tecnologia e Economia Criativa']);
        $this->actingAs($this->nucleo, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$direcao}/decisao", ['aprovar' => true])->assertOk();
        $this->actingAs($this->coordenadores['Tecnologia e Economia Criativa'], 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$direcao}/decisao", ['aprovar' => true])->assertOk();

        $this->actingAs($this->direcao, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$direcao}/decisao", ['aprovar' => false])
            ->assertStatus(422);
        $this->postJson("/api/svt/solicitacoes/{$direcao}/decisao", [
            'aprovar' => false,
            'motivo' => 'Preservar a visita restante para outra atividade.',
        ])->assertOk()->assertJsonPath('visita.etapa', 'recusada_direcao');

        $this->actingAs($this->nulog, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$direcao}/transporte", [
                'transporte_tipo' => 'Carro',
                'veiculo' => 'Carro 1',
                'placa' => 'ABC1D23',
                'data_transporte' => now()->addDay()->toDateString(),
                'horario_transporte' => '10:00',
                'motorista' => 'Lia',
            ])->assertStatus(422);
        $this->assertSame('recusada_direcao', VisitaTecnica::query()->find($direcao)->etapa);
    }

    public function test_cancelamento_so_pelo_instrutor_antes_da_coordenacao(): void
    {
        $rascunho = $this->actingAs($this->instrutor, 'sanctum')
            ->postJson('/api/svt/solicitacoes', $this->payload($this->turmas['Ambiente e Saúde'], ['enviar' => false]))
            ->assertCreated()
            ->json('visita.id');

        $this->postJson("/api/svt/solicitacoes/{$rascunho}/cancelar", ['motivo' => 'A turma não vai mais.'])
            ->assertOk()
            ->assertJsonPath('visita.etapa', 'cancelada');

        $enviada = $this->criarEnviada($this->turmas['Beleza e Cuidado Pessoal']);
        $this->actingAs($this->nucleo, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$enviada}/cancelar")
            ->assertStatus(422);
        $this->actingAs($this->instrutor, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$enviada}/cancelar")
            ->assertOk()
            ->assertJsonPath('visita.etapa', 'cancelada');
        $this->actingAs($this->nucleo, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$enviada}/decisao", ['aprovar' => true])
            ->assertStatus(422);

        $avancada = $this->criarEnviada($this->turmas['Gastronomia e Turismo']);
        $this->actingAs($this->nucleo, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$avancada}/decisao", ['aprovar' => true])->assertOk();
        $this->actingAs($this->instrutor, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$avancada}/cancelar", ['motivo' => 'Desisti'])
            ->assertStatus(422);
        $this->assertSame('cped', VisitaTecnica::query()->find($avancada)->etapa);
    }

    public function test_quem_esta_fora_da_etapa_nao_decide_nem_consulta(): void
    {
        $id = $this->criarEnviada($this->turmas['Ambiente e Saúde']);

        $this->actingAs($this->instrutor, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])
            ->assertStatus(422);
        $this->actingAs($this->direcao, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])
            ->assertStatus(422);
        $this->actingAs($this->nulog, 'sanctum')
            ->getJson("/api/svt/solicitacoes/{$id}")
            ->assertNotFound();
        $this->postJson("/api/svt/solicitacoes/{$id}/transporte", [
            'transporte_tipo' => 'Van',
            'veiculo' => 'Van cedo',
            'placa' => 'NUL0G12',
            'data_transporte' => now()->addDay()->toDateString(),
            'horario_transporte' => '08:00',
            'motorista' => 'Cedo',
        ])->assertStatus(422);

        auth()->forgetGuards();
        $this->getJson("/api/svt/solicitacoes/{$id}")->assertUnauthorized();
        $this->postJson('/api/svt/solicitacoes', $this->payload($this->turmas['Ambiente e Saúde']))
            ->assertUnauthorized();

        $this->assertSame('nucleo', VisitaTecnica::query()->find($id)->etapa);
    }

    public function test_limites_iniciais_da_ata_valem_quando_nao_existe_regra_do_tipo(): void
    {
        $this->actingAs($this->instrutor, 'sanctum');

        foreach ([
            'qualificacao' => 2,
            'aperfeicoamento' => 1,
            'aprendizagem' => 4,
        ] as $tipo => $limite) {
            $eixo = Eixo::query()->where('nome', 'Ambiente e Saúde')->first();
            $curso = Curso::query()->create([
                'eixo_id' => $eixo->id,
                'nome' => 'Curso '.$tipo,
                'tipo_curso' => $tipo,
                'carga_horaria' => 160,
                'ativo' => true,
            ]);
            $turma = Turma::query()->create([
                'curso_id' => $curso->id,
                'unidade_id' => $this->unidade->id,
                'instrutor_id' => $this->instrutor->id,
                'nome' => 'TURMA '.$tipo,
                'ativo' => true,
            ]);

            $this->getJson('/api/svt/cota?turma_id='.$turma->id)
                ->assertOk()
                ->assertJsonPath('limite', $limite)
                ->assertJsonPath('disponiveis', $limite);
        }
    }

    public function test_formulario_rejeita_data_horario_tamanho_e_placa(): void
    {
        $turma = $this->turmas['Ambiente e Saúde'];
        $this->actingAs($this->instrutor, 'sanctum')
            ->postJson('/api/svt/solicitacoes', $this->payload($turma, [
                'data_visita_prevista' => now()->subDay()->toDateString(),
            ]))->assertStatus(422)->assertJsonValidationErrors(['data_visita_prevista']);

        $this->postJson('/api/svt/solicitacoes', $this->payload($turma, [
            'horario_pretendido' => '9:00',
        ]))->assertStatus(422)->assertJsonValidationErrors(['horario_pretendido']);

        $this->postJson('/api/svt/solicitacoes', $this->payload($turma, [
            'local_visita' => str_repeat('A', 181),
        ]))->assertStatus(422)->assertJsonValidationErrors(['local_visita']);

        $id = $this->criarEnviada($turma);
        $this->actingAs($this->nucleo, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])->assertOk();
        $this->actingAs($this->coordenadores['Ambiente e Saúde'], 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])->assertOk();
        $this->actingAs($this->direcao, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])->assertOk();
        $this->actingAs($this->nulog, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/transporte", [
                'transporte_tipo' => 'Helicóptero',
                'veiculo' => 'Aéreo',
                'placa' => '!!!',
                'data_transporte' => now()->addDay()->toDateString(),
                'horario_transporte' => '08:00',
                'motorista' => 'Ninguém',
            ])->assertStatus(422)->assertJsonValidationErrors(['transporte_tipo', 'placa']);

        $this->assertSame('nulog', VisitaTecnica::query()->find($id)->etapa);
    }

    public function test_segunda_decisao_seguida_nao_avanca_duas_etapas(): void
    {
        $id = $this->criarEnviada($this->turmas['Beleza e Cuidado Pessoal']);
        $this->actingAs($this->nucleo, 'sanctum');

        $this->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])->assertOk();
        $this->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])->assertStatus(422);
        $this->postJson("/api/svt/solicitacoes/{$id}/decisao", [
            'aprovar' => false,
            'motivo' => 'Tentativa atrasada.',
        ])->assertStatus(422);

        $this->assertSame('cped', VisitaTecnica::query()->find($id)->etapa);
        $this->assertSame(1, DB::table('visita_eventos')->where('visita_tecnica_id', $id)->where('evento', 'aprovacao')->count());
    }

    public function test_indicadores_separam_solicitacao_de_visita_realizada_e_o_documento_respeita_o_escopo(): void
    {
        $id = $this->criarEnviada($this->turmas['Tecnologia e Economia Criativa']);

        $direcao = $this->actingAs($this->direcao, 'sanctum')
            ->getJson('/api/svt/relatorios')
            ->assertOk();
        $direcao->assertJsonPath('solicitacoes', 1);
        $direcao->assertJsonPath('realizadas', 0);
        $direcao->assertJsonPath('aguardando_aprovacao', 1);
        $this->assertCount(5, $direcao->json('por_eixo'));

        $coordenacao = $this->actingAs($this->coordenadores['Tecnologia e Economia Criativa'], 'sanctum')
            ->getJson('/api/svt/relatorios')
            ->assertOk();
        $coordenacao->assertJsonPath('solicitacoes', 1);
        $this->assertCount(1, $coordenacao->json('por_eixo'));
        $this->assertSame('Tecnologia e Economia Criativa', $coordenacao->json('por_eixo.0.nome'));

        $this->aprovarAteTransporte($id);
        $this->actingAs($this->instrutor, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/realizar")
            ->assertOk();

        $this->actingAs($this->direcao, 'sanctum')
            ->getJson('/api/svt/relatorios')
            ->assertOk()
            ->assertJsonPath('solicitacoes', 1)
            ->assertJsonPath('realizadas', 1)
            ->assertJsonPath('relatorios_entregues', 0)
            ->assertJsonPath('relatorios_pendentes', 1);

        $this->actingAs($this->instrutor, 'sanctum')
            ->getJson('/api/svt/solicitacoes?documento=pendente')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/svt/solicitacoes?documento=entregue')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($this->coordenadores['Gastronomia e Turismo'], 'sanctum')
            ->getJson('/api/svt/solicitacoes?documento=todos')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($this->nucleo, 'sanctum')
            ->getJson('/api/svt/solicitacoes?documento=pendente')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_historico_da_turma_usa_o_cadastro_e_nao_vaza_outro_eixo(): void
    {
        $tecnologia = $this->turmas['Tecnologia e Economia Criativa'];
        $id = $this->criarEnviada($tecnologia);
        $homonima = $this->criarEnviada($this->turmas['Gastronomia e Turismo']);
        VisitaTecnica::query()->whereKey($homonima)->update(['turma' => $tecnologia->nome]);
        VisitaTecnica::query()->create([
            'etapa' => 'cancelada',
            'status' => 'cancelada',
            'turma_id' => null,
            'turma' => $tecnologia->nome,
            'curso' => $tecnologia->curso?->nome,
            'eixo' => 'Tecnologia e Economia Criativa',
            'unidade' => $tecnologia->unidade?->nome,
            'criado_por' => $this->instrutor->id,
            'instrutor' => $this->instrutor->nome,
            'responsavel_area' => 'Coordenação de Tecnologia',
        ]);

        $admin = $this->usuario('Administrador', 'admin.historico@df.senac.br', 'Asa Norte');
        $historico = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/svt/turmas/{$tecnologia->id}/historico")
            ->assertOk();
        $ids = collect($historico->json('itens'))->pluck('id');
        $this->assertTrue($ids->contains($id));
        $this->assertFalse($ids->contains($homonima));
        $this->assertSame((int) now()->year, $historico->json('ano'));
        $this->assertSame(2, $historico->json('resumo.solicitacoes'));
        $this->assertSame(1, $historico->json('resumo.canceladas'));
        $this->assertSame(0, $historico->json('resumo.realizadas'));

        $this->getJson("/api/svt/turmas/{$tecnologia->id}/historico?ano=1999")
            ->assertOk()
            ->assertJsonPath('ano', 1999)
            ->assertJsonPath('resumo.solicitacoes', 0);

        $this->actingAs($this->instrutor, 'sanctum')
            ->getJson("/api/svt/turmas/{$tecnologia->id}/historico")
            ->assertForbidden();

        $this->actingAs($this->coordenadores['Gastronomia e Turismo'], 'sanctum')
            ->getJson("/api/svt/turmas/{$tecnologia->id}/historico")
            ->assertForbidden();

        $this->actingAs($this->direcao, 'sanctum')
            ->getJson("/api/svt/turmas/{$tecnologia->id}/historico")
            ->assertForbidden();

        $this->actingAs($this->direcao, 'sanctum')
            ->getJson("/api/svt/solicitacoes/{$id}")
            ->assertOk()
            ->assertJsonMissingPath('historico');
    }

    private function docxTemporario(): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'svt').'.docx';
        $zip = new \ZipArchive();
        $zip->open($caminho, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Teste</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        return $caminho;
    }

    private function criarEnviada(Turma $turma): int
    {
        return $this->actingAs($this->instrutor, 'sanctum')
            ->postJson('/api/svt/solicitacoes', $this->payload($turma))
            ->assertCreated()
            ->json('visita.id');
    }

    private function aprovarAteTransporte(int $id): void
    {
        $visita = VisitaTecnica::query()->findOrFail($id);
        $this->actingAs($this->nucleo, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])->assertOk();
        $this->actingAs($this->coordenadores[$visita->eixo], 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])->assertOk();
        $this->actingAs($this->direcao, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])->assertOk();
        $this->actingAs($this->nulog, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/transporte", [
                'transporte_tipo' => 'Van',
                'veiculo' => 'Van 2',
                'placa' => 'XYZ9A87',
                'data_transporte' => now()->addDay()->toDateString(),
                'horario_transporte' => '08:00',
                'motorista' => 'João',
            ])->assertOk();
    }

    private function payload(Turma $turma, array $extra = []): array
    {
        return array_merge([
            'enviar' => true,
            'turma_id' => $turma->id,
            'local_visita' => 'Local da '.$turma->nome,
            'data_visita_prevista' => now()->addDays(10)->toDateString(),
            'horario_pretendido' => '09:00',
            'atividade' => 'Observar a rotina do setor.',
            'objetivo' => 'Relacionar a prática com o curso.',
            'relacao_curricular' => 'Unidade curricular em andamento.',
        ], $extra);
    }

    private function usuario(string $perfil, string $email, string $unidade, ?string $area = null): Usuario
    {
        return Usuario::query()->create([
            'nome' => $email,
            'email' => $email,
            'senha' => Hash::make('senha'),
            'perfil' => $perfil,
            'status' => true,
            'unidade' => $unidade,
            'area' => $area,
        ]);
    }
}
