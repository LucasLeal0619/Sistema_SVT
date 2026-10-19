<?php

namespace Tests\Feature;

use App\Models\AuditoriaEvento;
use App\Models\Curso;
use App\Models\Eixo;
use App\Models\RegraVisita;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\Usuario;
use App\Models\VisitaEvento;
use App\Services\AuditoriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private Unidade $unidade;

    private Usuario $admin;

    private Usuario $instrutor;

    private Usuario $nucleo;

    private Usuario $coordenador;

    private Usuario $direcao;

    private Usuario $nulog;

    private Turma $turma;

    private RegraVisita $regra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unidade = Unidade::query()->create(['nome' => 'Asa Norte', 'ativo' => true]);
        $eixo = Eixo::query()->create(['nome' => 'Tecnologia e Economia Criativa', 'ativo' => true]);
        $curso = Curso::query()->create([
            'eixo_id' => $eixo->id,
            'nome' => 'Curso Técnico',
            'tipo_curso' => 'tecnico',
            'carga_horaria' => 1200,
            'ativo' => true,
        ]);
        $this->admin = $this->usuario('Administrador', 'Administrador', 'admin@df.senac.br', 'Asa Norte');
        $this->instrutor = $this->usuario('Instrutor', 'Ana Instrutora', 'instrutor@df.senac.br', 'Asa Norte');
        $this->nucleo = $this->usuario('Núcleo Pedagógico', 'Núcleo', 'nucleo@df.senac.br', 'Asa Norte');
        $this->coordenador = $this->usuario('Coordenação CPED', 'Coordenação', 'cped@df.senac.br', 'DEP/CPED', 'Coordenação de Tecnologia');
        $this->coordenador->eixos()->sync([$eixo->id]);
        $this->direcao = $this->usuario('Direção Pedagógica', 'Direção', 'direcao@df.senac.br', 'DEP/CPED');
        $this->nulog = $this->usuario('NULOG', 'Logística', 'nulog@df.senac.br', 'NULOG');
        $this->turma = Turma::query()->create([
            'curso_id' => $curso->id,
            'unidade_id' => $this->unidade->id,
            'instrutor_id' => $this->instrutor->id,
            'nome' => 'TEC-2026-A',
            'ativo' => true,
        ]);
        $this->regra = RegraVisita::query()->create([
            'tipo_curso' => 'tecnico',
            'limite' => 1,
            'vigencia_inicio' => '2026-01-01',
            'criterio' => 'realizadas_e_programadas',
            'ativo' => true,
        ]);
    }

    public function test_login_grava_sucesso_e_falha_sem_a_senha(): void
    {
        $this->postJson('/api/login', [
            'email' => 'instrutor@df.senac.br',
            'senha' => 'senha-que-nao-pode-aparecer',
        ])->assertStatus(422);

        $this->postJson('/api/login', [
            'email' => 'instrutor@df.senac.br',
            'senha' => 'senha-do-teste',
        ])->assertOk();

        $bruto = DB::table('auditoria_eventos')->get()->toJson();
        $this->assertStringNotContainsString('senha-que-nao-pode-aparecer', $bruto);
        $this->assertStringNotContainsString('senha-do-teste', $bruto);
        $this->assertStringNotContainsString($this->instrutor->fresh()->senha, $bruto);

        $this->assertDatabaseHas('auditoria_eventos', [
            'acao' => 'login',
            'resultado' => 'falha',
            'usuario_id' => $this->instrutor->id,
            'usuario_nome' => 'Ana Instrutora',
        ]);
        $this->assertDatabaseHas('auditoria_eventos', [
            'acao' => 'login',
            'resultado' => 'sucesso',
            'perfil' => 'Instrutor',
            'fuso' => 'UTC',
        ]);
    }

    public function test_somente_o_administrador_consulta_e_o_acesso_negado_fica_registrado(): void
    {
        $this->actingAs($this->instrutor, 'sanctum')
            ->getJson('/api/svt/auditoria')
            ->assertForbidden();

        $this->actingAs($this->instrutor, 'sanctum')
            ->getJson('/api/svt/auditoria/1')
            ->assertForbidden();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/svt/auditoria?resultado=negado')
            ->assertOk()
            ->assertJsonPath('indicadores.negado', 2)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.acao', 'acesso_negado');

        $id = AuditoriaEvento::query()->where('acao', 'acesso_negado')->value('id');
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/svt/auditoria/'.$id)
            ->assertOk()
            ->assertJsonPath('evento.resultado', 'negado')
            ->assertJsonPath('evento.usuario_nome', 'Ana Instrutora');
    }

    public function test_os_seis_perfis_geram_eventos_com_filtro_paginacao_e_cadeia(): void
    {
        $id = $this->actingAs($this->instrutor, 'sanctum')
            ->postJson('/api/svt/solicitacoes', $this->payload())
            ->assertCreated()
            ->json('visita.id');

        $this->actingAs($this->nucleo, 'sanctum')
            ->postJson("/api/svt/solicitacoes/{$id}/decisao", ['aprovar' => true])->assertOk();
        $this->actingAs($this->coordenador, 'sanctum')
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

        $this->actingAs($this->instrutor, 'sanctum')
            ->postJson('/api/svt/solicitacoes', $this->payload([
                'justificativa' => 'Visita extra de laboratório.',
            ]))->assertCreated();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/svt/cadastros/regras/'.$this->regra->id, [
                'tipo_curso' => 'tecnico',
                'limite' => 2,
                'vigencia_inicio' => '2026-01-01',
                'ativo' => true,
            ])->assertOk();

        $visita = \App\Models\VisitaTecnica::query()->findOrFail($id);
        $visita->relatorio_arquivo = 'visitas/'.$id.'/relatorio.pdf';
        $visita->save();
        $outro = $this->usuario('Instrutor', 'Outro', 'outro@df.senac.br', 'Asa Norte');
        $this->actingAs($outro, 'sanctum')
            ->get('/api/svt/solicitacoes/'.$id.'/relatorio/arquivo')
            ->assertNotFound();

        $this->assertTrue(app(AuditoriaService::class)->cadeiaIntegra());
        $this->assertSame(2, VisitaEvento::query()->where('evento', 'envio')->count());
        $this->assertGreaterThanOrEqual(2, AuditoriaEvento::query()->where('acao', 'envio')->count());

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/svt/auditoria?perfil=NULOG&categoria=logistica&acao=transporte_definido')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.usuario_nome', 'Logística');

        $transporte = AuditoriaEvento::query()->where('acao', 'transporte_definido')->firstOrFail();
        $this->assertSame('XYZ9A87', $transporte->novo['placa'] ?? null);
        $this->assertSame('Asa Norte', $transporte->unidade);
        $this->assertSame('Tecnologia e Economia Criativa', $transporte->eixo);

        $extra = AuditoriaEvento::query()->where('acao', 'envio')->orderByDesc('id')->firstOrFail();
        $this->assertTrue($extra->novo['extraordinaria'] ?? false);
        $this->assertSame('Visita extra de laboratório.', $extra->novo['justificativa'] ?? null);

        $this->assertDatabaseHas('auditoria_eventos', [
            'acao' => 'limite_alterado',
            'categoria' => 'configuracoes',
            'entidade_id' => (string) $this->regra->id,
        ]);
        $this->assertDatabaseHas('regras_visita', ['id' => $this->regra->id, 'limite' => 2]);
        $this->assertDatabaseHas('auditoria_eventos', [
            'acao' => 'download_relatorio',
            'resultado' => 'negado',
            'usuario_nome' => 'Outro',
        ]);

        foreach (['Instrutor', 'Núcleo Pedagógico', 'Coordenação CPED', 'Direção Pedagógica', 'NULOG', 'Administrador'] as $perfil) {
            $this->assertTrue(
                AuditoriaEvento::query()->where('perfil', $perfil)->exists(),
                'Sem evento do perfil '.$perfil
            );
        }

        $pagina = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/svt/auditoria?per_page=2&page=2')
            ->assertOk()
            ->json();
        $this->assertCount(2, $pagina['data']);
        $this->assertGreaterThan(2, $pagina['meta']['last_page']);
        $this->assertNotSame($pagina['data'][0]['id'], AuditoriaEvento::query()->orderByDesc('id')->value('id'));
    }

    public function test_redefinicao_de_senha_e_o_nome_historico_permanecem_sem_segredo(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/usuarios', [
                'nome' => 'Carla',
                'email' => 'carla@df.senac.br',
                'senha' => 'senha-inicial-carla',
                'cpf' => '52998224725',
                'perfil' => 'Consultor',
                'status' => true,
                'unidade' => 'Asa Norte',
                'area' => 'Consulta',
                'telefone' => '61999999999',
            ])->assertCreated();

        $carla = Usuario::query()->where('email', 'carla@df.senac.br')->firstOrFail();
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/usuarios/'.$carla->id, [
                'nome' => 'Carla Renomeada',
                'email' => 'carla@df.senac.br',
                'senha' => 'senha-nova-carla',
                'cpf' => '52998224725',
                'perfil' => 'Consultor',
                'status' => true,
                'unidade' => 'Asa Norte',
                'area' => 'Consulta',
                'telefone' => '61999999999',
            ])->assertOk();

        $bruto = DB::table('auditoria_eventos')->get()->toJson();
        $this->assertStringNotContainsString('senha-inicial-carla', $bruto);
        $this->assertStringNotContainsString('senha-nova-carla', $bruto);
        $this->assertStringNotContainsString($carla->fresh()->senha, $bruto);
        $this->assertDatabaseHas('auditoria_eventos', [
            'acao' => 'senha_redefinida',
            'entidade_id' => (string) $carla->id,
        ]);
        $this->assertDatabaseHas('auditoria_eventos', [
            'acao' => 'usuario_criado',
            'usuario_nome' => 'Administrador',
        ]);

        $criacao = AuditoriaEvento::query()->where('acao', 'usuario_criado')->firstOrFail();
        $this->assertSame('Carla', $criacao->novo['nome'] ?? null);
        $this->admin->update(['nome' => 'Nome que não reescreve o passado']);
        $criacao->refresh();
        $this->assertSame('Administrador', $criacao->usuario_nome);
    }

    public function test_o_registro_nao_pode_ser_alterado_nem_apagado_pelo_modelo(): void
    {
        $evento = app(AuditoriaService::class)->registrar(
            categoria: 'seguranca',
            acao: 'teste',
            resultado: 'sucesso',
            usuario: $this->admin,
        );

        try {
            $evento->acao = 'alterado';
            $evento->save();
            $this->fail('A auditoria aceitou alteração.');
        } catch (RuntimeException $falha) {
            $this->assertSame('O registro de auditoria é imutável.', $falha->getMessage());
        }

        try {
            $evento->delete();
            $this->fail('A auditoria aceitou exclusão.');
        } catch (RuntimeException $falha) {
            $this->assertSame('O registro de auditoria não pode ser apagado por este caminho.', $falha->getMessage());
        }

        $this->assertSame('teste', $evento->fresh()->acao);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'enviar' => true,
            'turma_id' => $this->turma->id,
            'local_visita' => 'Laboratório',
            'data_visita_prevista' => now()->addDays(10)->toDateString(),
            'horario_pretendido' => '09:00',
            'atividade' => 'Observar a rotina do setor.',
            'objetivo' => 'Relacionar a prática com o curso.',
            'relacao_curricular' => 'Unidade curricular em andamento.',
        ], $extra);
    }

    private function usuario(string $perfil, string $nome, string $email, string $unidade, ?string $area = null): Usuario
    {
        return Usuario::query()->create([
            'nome' => $nome,
            'email' => $email,
            'senha' => Hash::make('senha-do-teste'),
            'perfil' => $perfil,
            'status' => true,
            'unidade' => $unidade,
            'area' => $area,
        ]);
    }
}
