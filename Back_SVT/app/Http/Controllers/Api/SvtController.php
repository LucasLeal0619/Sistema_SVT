<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\PaginatesIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\SvtSolicitacaoRequest;
use App\Models\Curso;
use App\Models\Eixo;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\Usuario;
use App\Models\VisitaEvento;
use App\Models\VisitaTecnica;
use App\Services\AuditoriaService;
use App\Services\SvtFluxoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class SvtController extends Controller
{
    use PaginatesIndex;

    public function __construct(
        private readonly SvtFluxoService $fluxo,
        private readonly AuditoriaService $auditoria,
    ) {}

    public function catalogo(): JsonResponse
    {
        return response()->json($this->opcoes());
    }

    public function cota(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'turma_id' => ['required_without:turma', 'integer', 'exists:turmas,id'],
            'turma' => ['required_without:turma_id', 'string', 'max:120'],
            'curso' => ['required_without:turma_id', 'string', 'max:180'],
        ]);

        $turma = isset($dados['turma_id'])
            ? Turma::query()->with('curso.eixo', 'unidade')->findOrFail($dados['turma_id'])
            : Turma::query()->with('curso.eixo', 'unidade')
                ->where('nome', $dados['turma'])
                ->whereHas('curso', fn ($query) => $query->where('nome', $dados['curso']))
                ->first();

        if (! $turma) {
            return response()->json([
                'message' => 'Turma não cadastrada. O administrador precisa vincular a turma antes da solicitação.',
            ], 422);
        }

        /** @var Usuario $user */
        $user = $request->user();
        if ($user->perfil === Usuario::PERFIL_INSTRUTOR && (int) $turma->instrutor_id !== (int) $user->id) {
            return response()->json(['message' => 'Esta turma não está vinculada a você.'], 403);
        }

        return response()->json($this->fluxo->quadro($turma));
    }

    public function turmas(Request $request): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();
        $query = Turma::query()
            ->with(['curso.eixo:id,nome', 'unidade:id,nome'])
            ->where('ativo', true)
            ->orderBy('nome');

        if ($user->perfil === Usuario::PERFIL_INSTRUTOR) {
            $query->where('instrutor_id', $user->id);
        }

        $turmas = $query->get()->map(fn (Turma $turma) => [
            'id' => $turma->id,
            'nome' => $turma->nome,
            'unidade' => $turma->unidade?->nome,
            'curso' => $turma->curso?->nome,
            'tipo_curso' => $turma->curso?->tipo_curso,
            'tipo_label' => config('svt.tipos.'.$turma->curso?->tipo_curso.'.label'),
            'eixo' => $turma->curso?->eixo?->nome,
            'carga_horaria' => $turma->curso?->carga_horaria,
        ]);

        return response()->json(['data' => $turmas]);
    }

    public function painel(Request $request): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();
        $fila = VisitaTecnica::query()->whereNotNull('etapa');
        $this->fluxo->aplicarFila($fila, $user);

        $base = VisitaTecnica::query()->whereNotNull('etapa');
        $this->fluxo->restringirConsulta($base, $user);
        $porEtapa = (clone $base)
            ->selectRaw('etapa, count(*) as total')
            ->groupBy('etapa')
            ->pluck('total', 'etapa');

        $recentes = (clone $base)
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'turma', 'local_visita', 'etapa', 'status', 'instrutor', 'curso', 'unidade', 'eixo', 'criado_por', 'responsavel_area', 'turma_id', 'ficticio'])
            ->map(fn (VisitaTecnica $visita) => $this->apresentar($visita, $user));

        $reprovadas = (int) ($porEtapa['recusada'] ?? 0)
            + (int) ($porEtapa['recusada_nucleo'] ?? 0)
            + (int) ($porEtapa['recusada_cped'] ?? 0)
            + (int) ($porEtapa['recusada_direcao'] ?? 0);
        $emTramitacao = (int) ($porEtapa['rascunho'] ?? 0)
            + (int) ($porEtapa['nucleo'] ?? 0)
            + (int) ($porEtapa['cped'] ?? 0)
            + (int) ($porEtapa['direcao'] ?? 0)
            + (int) ($porEtapa['nulog'] ?? 0);

        return response()->json([
            'em_fluxo' => (int) ($porEtapa['nucleo'] ?? 0) + (int) ($porEtapa['cped'] ?? 0) + (int) ($porEtapa['direcao'] ?? 0) + (int) ($porEtapa['nulog'] ?? 0) + (int) ($porEtapa['aprovada'] ?? 0),
            'realizadas' => (int) ($porEtapa['realizada'] ?? 0) + (int) ($porEtapa['relatorio'] ?? 0),
            'recusadas' => $reprovadas,
            'por_etapa' => [
                'nucleo' => (int) ($porEtapa['nucleo'] ?? 0),
                'cped' => (int) ($porEtapa['cped'] ?? 0),
                'direcao' => (int) ($porEtapa['direcao'] ?? 0),
                'nulog' => (int) ($porEtapa['nulog'] ?? 0),
            ],
            'resumo' => [
                'pendentes' => $user->perfil === Usuario::PERFIL_INSTRUTOR ? $emTramitacao : $fila->count(),
                'reprovadas' => $reprovadas,
                'transportes' => (int) ($porEtapa['aprovada'] ?? 0),
                'realizadas' => (int) ($porEtapa['realizada'] ?? 0) + (int) ($porEtapa['relatorio'] ?? 0),
                'relatorios_pendentes' => (int) ($porEtapa['realizada'] ?? 0),
                'relatorios_entregues' => (int) ($porEtapa['relatorio'] ?? 0),
                'aguardando_transporte' => (int) ($porEtapa['nulog'] ?? 0),
                'em_tramitacao' => $emTramitacao,
                'canceladas' => (int) ($porEtapa['cancelada'] ?? 0),
                'solicitacoes' => (clone $base)->count(),
                'nucleo' => (int) ($porEtapa['nucleo'] ?? 0),
                'cped' => (int) ($porEtapa['cped'] ?? 0),
                'direcao' => (int) ($porEtapa['direcao'] ?? 0),
            ],
            'minha_fila' => $fila->count(),
            'itens' => (clone $fila)->orderByDesc('id')->limit(3)->get()
                ->map(fn (VisitaTecnica $visita) => [
                    'id' => $visita->id,
                    'turma' => $visita->turma,
                    'local_visita' => $visita->local_visita,
                    'etapa_label' => config("svt.etapas.{$visita->etapa}", $visita->status),
                ]),
            'recentes' => $recentes,
            'por_eixo' => $this->eixosVisiveis(clone $base, $user),
            'equipe' => $user->isAdministrador()
                ? collect($this->atividadeDaEquipe())->filter(fn (array $pessoa) => $pessoa['pendencias'] > 0)->take(8)->values()
                : [],
            'pode_criar' => $this->fluxo->podeCriar($user),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();
        $query = VisitaTecnica::query()->whereNotNull('etapa')->orderByDesc('id');
        $this->fluxo->restringirConsulta($query, $user);

        if ($request->boolean('fila')) {
            $this->fluxo->aplicarFila($query, $user);
        }

        $this->aplicarFiltrosDaLista($request, $query);

        $pagina = $this->paginar($query, $request, 15);

        return response()->json([
            'data' => $pagina->getCollection()->map(fn (VisitaTecnica $visita) => $this->apresentar($visita, $user))->values(),
            'meta' => $this->metaPaginacao($pagina),
            'pode_criar' => $this->fluxo->podeCriar($user),
        ]);
    }

    public function store(SvtSolicitacaoRequest $request): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();
        $visita = $request->boolean('enviar', true)
            ? $this->fluxo->solicitar($request->validated(), $user)
            : $this->fluxo->guardarRascunho($request->validated(), $user);

        return response()->json([
            'message' => $visita->etapa === 'rascunho'
                ? 'Rascunho salvo.'
                : 'Solicitação enviada ao núcleo pedagógico.',
            'visita' => $this->apresentar($visita, $user),
        ], 201);
    }

    public function update(SvtSolicitacaoRequest $request, VisitaTecnica $visitaTecnica): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();
        $visita = $this->fluxo->atualizarRascunho(
            $visitaTecnica,
            $request->validated(),
            $user,
            $request->boolean('enviar')
        );

        return response()->json([
            'message' => $visita->etapa === 'rascunho' ? 'Rascunho atualizado.' : 'Solicitação enviada ao núcleo pedagógico.',
            'visita' => $this->apresentar($visita, $user),
        ]);
    }

    public function cancelar(Request $request, VisitaTecnica $visitaTecnica): JsonResponse
    {
        $dados = $request->validate([
            'motivo' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var Usuario $user */
        $user = $request->user();
        $visita = $this->fluxo->cancelar($visitaTecnica, $user, $dados['motivo'] ?? null);

        return response()->json([
            'message' => 'Solicitação cancelada.',
            'visita' => $this->apresentar($visita, $user),
        ]);
    }

    public function realizar(Request $request, VisitaTecnica $visitaTecnica): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();
        $visita = $this->fluxo->marcarRealizada($visitaTecnica, $user);

        return response()->json([
            'message' => 'Visita marcada como realizada. O relatório ainda pode ser anexado.',
            'visita' => $this->apresentar($visita, $user),
        ]);
    }

    public function show(Request $request, VisitaTecnica $visitaTecnica): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();

        if (! $this->fluxo->podeConsultar($visitaTecnica, $user)) {
            abort(404);
        }

        return response()->json([
            'visita' => $this->apresentar($visitaTecnica, $user),
            'eventos' => $visitaTecnica->eventos()->orderBy('id')->get()->map(fn (VisitaEvento $evento) => [
                'id' => $evento->id,
                'perfil' => $evento->perfil,
                'evento' => $evento->evento,
                'descricao' => $this->descreverEvento($evento),
                'texto' => $evento->texto,
                'etapa_anterior' => $evento->etapa_anterior,
                'etapa_nova' => $evento->etapa_nova,
                'created_at' => $evento->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function decidir(Request $request, VisitaTecnica $visitaTecnica): JsonResponse
    {
        $dados = $request->validate([
            'aprovar' => ['required', 'boolean'],
            'motivo' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var Usuario $user */
        $user = $request->user();
        $visita = $this->fluxo->decidir($visitaTecnica, $user, (bool) $dados['aprovar'], $dados['motivo'] ?? null);

        return response()->json([
            'message' => $dados['aprovar'] ? 'Solicitação encaminhada.' : 'Solicitação recusada.',
            'visita' => $this->apresentar($visita, $user),
        ]);
    }

    public function transporte(Request $request, VisitaTecnica $visitaTecnica): JsonResponse
    {
        $dados = $request->validate([
            'transporte_tipo' => ['required', 'string', Rule::in(config('svt.transportes'))],
            'veiculo' => ['required', 'string', 'max:80'],
            'data_transporte' => ['required', 'date'],
            'horario_transporte' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'horario_retorno' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'motorista' => ['required', 'string', 'max:150'],
            'info_transporte' => ['nullable', 'string', 'max:2000'],
            'placa' => ['required', 'string', 'regex:/^[A-Za-z0-9 -]{6,10}$/'],
        ]);

        /** @var Usuario $user */
        $user = $request->user();
        $visita = $this->fluxo->registrarTransporte($visitaTecnica, $user, $dados);

        return response()->json([
            'message' => 'Transporte registrado. A unidade já pode consultar esses dados.',
            'visita' => $this->apresentar($visita, $user),
        ]);
    }

    public function relatorio(Request $request, VisitaTecnica $visitaTecnica): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();

        if (! $this->fluxo->podeRelatorio($visitaTecnica, $user)) {
            return response()->json([
                'message' => 'O relatório é anexado pelo instrutor depois que a visita foi realizada.',
            ], 422);
        }

        $dados = $request->validate([
            'arquivo' => ['required', 'file', 'max:10240', function (string $atributo, mixed $arquivo, \Closure $falhar): void {
                $motivo = $arquivo instanceof UploadedFile
                    ? $this->motivoArquivoRelatorio($arquivo)
                    : 'Envie o relatório em PDF, DOC ou DOCX.';
                if ($motivo !== null) {
                    $falhar($motivo);
                }
            }],
            'relatorio_texto' => ['nullable', 'string', 'max:5000'],
        ], [
            'arquivo.required' => 'Anexe o relatório em PDF, DOC ou DOCX.',
            'arquivo.max' => 'O relatório pode ter no máximo 10 MB.',
        ]);

        $arquivo = $request->file('arquivo');
        $extensao = strtolower($arquivo->getClientOriginalExtension());
        $caminho = $arquivo->storeAs(
            "visitas/{$visitaTecnica->id}",
            Str::uuid()->toString().'.'.$extensao,
            'local'
        );
        if (! $caminho) {
            return response()->json(['message' => 'Não foi possível guardar o relatório.'], 500);
        }

        $anterior = $visitaTecnica->relatorio_arquivo;
        $visita = $this->fluxo->anexarRelatorio($visitaTecnica, $user, $dados['relatorio_texto'] ?? null, $caminho);
        if ($anterior && $anterior !== $caminho) {
            Storage::disk('local')->delete($anterior);
            Storage::disk('public')->delete($anterior);
        }

        return response()->json([
            'message' => 'Relatório da visita anexado.',
            'visita' => $this->apresentar($visita, $user),
        ]);
    }

    public function baixarRelatorio(Request $request, VisitaTecnica $visitaTecnica): BinaryFileResponse
    {
        /** @var Usuario $user */
        $user = $request->user();

        if (! $this->fluxo->podeConsultar($visitaTecnica, $user) || blank($visitaTecnica->relatorio_arquivo)) {
            $this->auditoria->registrar(
                categoria: 'documentos',
                acao: 'download_relatorio',
                resultado: 'negado',
                usuario: $user,
                entidadeTipo: 'visita_tecnica',
                entidadeId: (string) $visitaTecnica->id,
                unidade: $visitaTecnica->unidade,
                eixo: $visitaTecnica->eixo,
            );
            abort(404);
        }

        $caminho = $visitaTecnica->relatorio_arquivo;
        $disco = Storage::disk('local')->exists($caminho)
            ? 'local'
            : (Storage::disk('public')->exists($caminho) ? 'public' : null);

        if ($disco === null) {
            $this->auditoria->registrar(
                categoria: 'documentos',
                acao: 'download_relatorio',
                resultado: 'falha',
                usuario: $user,
                entidadeTipo: 'visita_tecnica',
                entidadeId: (string) $visitaTecnica->id,
                unidade: $visitaTecnica->unidade,
                eixo: $visitaTecnica->eixo,
            );
            abort(404);
        }

        $extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
        $nome = 'relatorio-visita-'.$visitaTecnica->id.'.'.$extensao;
        $absoluto = Storage::disk($disco)->path($caminho);
        $tipo = match ($extensao) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
        $disposicao = $extensao === 'pdf' ? 'inline' : 'attachment';
        $this->auditoria->registrar(
            categoria: 'documentos',
            acao: 'download_relatorio',
            resultado: 'sucesso',
            usuario: $user,
            entidadeTipo: 'visita_tecnica',
            entidadeId: (string) $visitaTecnica->id,
            unidade: $visitaTecnica->unidade,
            eixo: $visitaTecnica->eixo,
            novo: ['extensao' => $extensao],
        );

        return response()->file($absoluto, [
            'Content-Type' => $tipo,
            'Content-Disposition' => $disposicao.'; filename="'.$nome.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function relatorios(Request $request): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();
        $base = VisitaTecnica::query()->whereNotNull('etapa');
        $this->fluxo->restringirConsulta($base, $user);
        $this->aplicarFiltrosDaLista($request, $base);

        $responsaveis = config('svt.responsaveis');
        $eixoTecnologia = array_search('Coordenação de Tecnologia', $responsaveis, true);
        $eixoGestao = array_search('Coordenação de Gestão', $responsaveis, true);
        $porEtapa = (clone $base)
            ->selectRaw('etapa, count(*) as total')
            ->groupBy('etapa')
            ->pluck('total', 'etapa');
        $quantidade = fn (string $etapa): int => (int) ($porEtapa[$etapa] ?? 0);
        $total = (clone $base)->count();
        $reprovadas = $quantidade('recusada')
            + $quantidade('recusada_nucleo')
            + $quantidade('recusada_cped')
            + $quantidade('recusada_direcao');

        return response()->json([
            'total' => $total,
            'solicitacoes' => $total,
            'realizadas' => $quantidade('realizada') + $quantidade('relatorio'),
            'aguardando_aprovacao' => $quantidade('nucleo') + $quantidade('cped') + $quantidade('direcao'),
            'reprovadas' => $reprovadas,
            'transporte_definido' => $quantidade('aprovada'),
            'relatorios_entregues' => $quantidade('relatorio'),
            'relatorios_pendentes' => $quantidade('realizada'),
            'tecnologia' => $eixoTecnologia === false ? 0 : (clone $base)->where('eixo', $eixoTecnologia)->count(),
            'gestao' => $eixoGestao === false ? 0 : (clone $base)->where('eixo', $eixoGestao)->count(),
            'em_andamento' => $quantidade('rascunho') + $quantidade('nucleo') + $quantidade('cped') + $quantidade('direcao') + $quantidade('nulog'),
            'aguardando_transporte' => $quantidade('nulog'),
            'canceladas' => $quantidade('cancelada'),
            'por_unidade' => $this->distribuir(clone $base, 'unidade'),
            'por_curso' => $this->distribuir(clone $base, 'curso'),
            'por_eixo' => $this->eixosVisiveis(clone $base, $user),
            'por_etapa' => $this->agrupar(clone $base, 'etapa'),
            'por_mes' => $this->porMes(clone $base),
        ]);
    }

    public function historicoTurma(Request $request, Turma $turma): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();
        $turma->loadMissing('curso.eixo', 'unidade');

        if (! $user->isAdministrador()) {
            abort(403);
        }

        $ano = $request->filled('ano') ? $request->integer('ano') : null;
        if ($ano !== null && ($ano < 1990 || $ano > 2100)) {
            $ano = null;
        }

        return response()->json($this->montarHistorico($turma->id, $turma->nome, $user, null, $ano));
    }

    private function aplicarFiltrosDaLista(Request $request, Builder $query): void
    {
        if ($request->filled('busca')) {
            $busca = addcslashes($request->string('busca')->toString(), '%_\\');
            $query->where(function ($q) use ($busca) {
                $q->where('turma', 'like', "%{$busca}%")
                    ->orWhere('curso', 'like', "%{$busca}%")
                    ->orWhere('unidade', 'like', "%{$busca}%")
                    ->orWhere('local_visita', 'like', "%{$busca}%")
                    ->orWhere('instrutor', 'like', "%{$busca}%")
                    ->orWhere('eixo', 'like', "%{$busca}%");
            });
        }

        if ($request->filled('etapa')) {
            $query->where('etapa', $request->string('etapa')->toString());
        } elseif ($request->filled('situacao')) {
            $etapas = $this->etapasDaSituacao($request->string('situacao')->toString());
            if ($etapas !== []) {
                $query->whereIn('etapa', $etapas);
            }
        } elseif ($request->filled('documento')) {
            $etapas = match ($request->string('documento')->toString()) {
                'pendente' => ['realizada'],
                'entregue' => ['relatorio'],
                'todos' => ['realizada', 'relatorio'],
                default => [],
            };
            if ($etapas !== []) {
                $query->whereIn('etapa', $etapas);
            }
        }

        if ($request->filled('unidade')) {
            $query->where('unidade', $request->string('unidade')->toString());
        }

        if ($request->filled('eixo')) {
            $query->where('eixo', $request->string('eixo')->toString());
        }

        if ($request->filled('instrutor')) {
            $query->where('instrutor', $request->string('instrutor')->toString());
        }

        if ($request->filled('de')) {
            $query->whereDate('data_visita_prevista', '>=', $request->string('de')->toString());
        }

        if ($request->filled('ate')) {
            $query->whereDate('data_visita_prevista', '<=', $request->string('ate')->toString());
        }

        if ($request->filled('turma')) {
            $query->where('turma', $request->string('turma')->toString());
        }

        if ($request->filled('curso')) {
            $query->where('curso', $request->string('curso')->toString());
        }
    }

    /** @return list<string> */
    private function etapasDaSituacao(string $situacao): array
    {
        return match ($situacao) {
            'tramitacao' => ['rascunho', 'nucleo', 'cped', 'direcao', 'nulog'],
            'aprovacao' => ['nucleo', 'cped', 'direcao'],
            'reprovadas' => ['recusada', 'recusada_nucleo', 'recusada_cped', 'recusada_direcao'],
            'transporte' => ['aprovada'],
            'realizadas' => ['realizada', 'relatorio'],
            'relatorio_pendente' => ['realizada'],
            'relatorio_entregue' => ['relatorio'],
            'logistica' => ['nulog'],
            default => [],
        };
    }

    /** @return list<array{nome: string, total: int}> */
    private function eixosVisiveis(Builder $base, Usuario $user): array
    {
        $contagem = (clone $base)
            ->selectRaw('eixo as nome, count(*) as total')
            ->groupBy('eixo')
            ->pluck('total', 'nome');
        $realizadas = (clone $base)
            ->whereIn('etapa', ['realizada', 'relatorio'])
            ->selectRaw('eixo as nome, count(*) as total')
            ->groupBy('eixo')
            ->pluck('total', 'nome');
        $responsaveis = config('svt.responsaveis');
        $globais = in_array($user->perfil, [
            Usuario::PERFIL_ADMINISTRADOR,
            Usuario::PERFIL_DIRECAO,
            Usuario::PERFIL_EDITOR,
            Usuario::PERFIL_CONSULTOR,
        ], true);

        if ($globais) {
            $nomes = array_keys($responsaveis);
        } elseif ($user->perfil === Usuario::PERFIL_CPED) {
            $areas = $user->areasResponsaveis();
            $nomes = array_keys(array_filter(
                $responsaveis,
                fn ($area) => in_array($area, $areas, true)
            ));
        } else {
            $nomes = $contagem->keys()->filter()->values()->all();
        }

        return collect($nomes)->map(fn ($nome) => [
            'nome' => (string) $nome,
            'total' => (int) ($contagem[$nome] ?? 0),
            'realizadas' => (int) ($realizadas[$nome] ?? 0),
        ])->all();
    }

    /**
     * @return array{ano: int, anos: list<int>, resumo: array<string, int>, itens: list<array<string, mixed>>}
     */
    private function montarHistorico(?int $turmaId, ?string $nome, Usuario $user, ?int $exceto = null, ?int $ano = null): array
    {
        $query = VisitaTecnica::query()->whereNotNull('etapa')->where(function ($grupo) use ($turmaId, $nome) {
            if ($turmaId) {
                $grupo->where('turma_id', $turmaId);
                if ($nome) {
                    $grupo->orWhere(function ($legado) use ($nome) {
                        $legado->whereNull('turma_id')->where('turma', $nome);
                    });
                }

                return;
            }

            $grupo->where('turma', $nome ?: '');
        });

        if ($exceto) {
            $query->where('id', '!=', $exceto);
        }

        $this->fluxo->restringirConsulta($query, $user);
        $expressao = $query->getConnection()->getDriverName() === 'sqlite'
            ? "cast(strftime('%Y', coalesce(data_visita_prevista, data_solicitacao, created_at)) as integer)"
            : 'YEAR(COALESCE(data_visita_prevista, data_solicitacao, created_at))';
        $anos = (clone $query)
            ->selectRaw("{$expressao} as ano")
            ->groupBy('ano')
            ->orderByDesc('ano')
            ->pluck('ano')
            ->map(fn ($valor) => (int) $valor)
            ->filter()
            ->values();
        $anoEscolhido = $ano ?: (int) ($anos->first() ?: now()->year);

        if ($anos->doesntContain($anoEscolhido)) {
            $anos->prepend($anoEscolhido);
        }

        $doAno = (clone $query)->whereRaw("{$expressao} = ?", [$anoEscolhido]);
        $porEtapa = (clone $doAno)->selectRaw('etapa, count(*) as total')->groupBy('etapa')->pluck('total', 'etapa');
        $quantidade = fn (string $etapa): int => (int) ($porEtapa[$etapa] ?? 0);
        $reprovadas = $quantidade('recusada') + $quantidade('recusada_nucleo') + $quantidade('recusada_cped') + $quantidade('recusada_direcao');

        $itens = (clone $doAno)
            ->orderByDesc('data_visita_prevista')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (VisitaTecnica $visita) => $this->apresentar($visita, $user))
            ->values()
            ->all();

        return [
            'ano' => $anoEscolhido,
            'anos' => $anos->all(),
            'resumo' => [
                'solicitacoes' => (clone $doAno)->count(),
                'realizadas' => $quantidade('realizada') + $quantidade('relatorio'),
                'tramitacao' => $quantidade('rascunho') + $quantidade('nucleo') + $quantidade('cped') + $quantidade('direcao') + $quantidade('nulog'),
                'reprovadas' => $reprovadas,
                'canceladas' => $quantidade('cancelada'),
                'transporte' => $quantidade('aprovada'),
                'relatorios_entregues' => $quantidade('relatorio'),
                'relatorios_pendentes' => $quantidade('realizada'),
            ],
            'itens' => $itens,
        ];
    }

    /** @return list<array{nome: string, total: int, realizadas: int}> */
    private function distribuir(Builder $base, string $coluna): array
    {
        $totais = (clone $base)
            ->selectRaw("{$coluna} as nome, count(*) as total")
            ->groupBy($coluna)
            ->pluck('total', 'nome');
        $realizadas = (clone $base)
            ->whereIn('etapa', ['realizada', 'relatorio'])
            ->selectRaw("{$coluna} as nome, count(*) as total")
            ->groupBy($coluna)
            ->pluck('total', 'nome');

        return $totais
            ->map(fn ($total, $nome) => [
                'nome' => $nome ?: 'Sem informação',
                'total' => (int) $total,
                'realizadas' => (int) ($realizadas[$nome] ?? 0),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /** @return list<array{mes: string, solicitacoes: int, realizadas: int, cota_provisoria: int}> */
    private function porMes(Builder $base): array
    {
        $sqlite = $base->getConnection()->getDriverName() === 'sqlite';
        $criacao = $sqlite ? "strftime('%Y-%m', data_solicitacao)" : "DATE_FORMAT(data_solicitacao, '%Y-%m')";
        $visita = $sqlite
            ? "strftime('%Y-%m', coalesce(realizada_em, data_visita_prevista))"
            : "DATE_FORMAT(COALESCE(realizada_em, data_visita_prevista), '%Y-%m')";

        $solicitacoes = (clone $base)
            ->whereNotNull('data_solicitacao')
            ->selectRaw("{$criacao} as mes, count(*) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');
        $realizadas = (clone $base)
            ->whereIn('etapa', ['realizada', 'relatorio'])
            ->selectRaw("{$visita} as mes, count(*) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');
        $cota = (clone $base)
            ->whereIn('etapa', config('svt.cota.consomem'))
            ->selectRaw("{$criacao} as mes, count(*) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');

        return $solicitacoes->keys()
            ->merge($realizadas->keys())
            ->merge($cota->keys())
            ->unique()
            ->sort()
            ->slice(-12)
            ->values()
            ->map(fn ($mes) => [
                'mes' => (string) $mes,
                'solicitacoes' => (int) ($solicitacoes[$mes] ?? 0),
                'realizadas' => (int) ($realizadas[$mes] ?? 0),
                'cota_provisoria' => (int) ($cota[$mes] ?? 0),
            ])
            ->all();
    }

    private function motivoArquivoRelatorio(UploadedFile $arquivo): ?string
    {
        $extensao = strtolower($arquivo->getClientOriginalExtension());
        if (! in_array($extensao, ['pdf', 'doc', 'docx'], true)) {
            return 'Envie o relatório em PDF, DOC ou DOCX.';
        }

        $caminho = $arquivo->getRealPath();
        if ($caminho === false || ! is_file($caminho)) {
            return 'Não foi possível ler o arquivo enviado.';
        }

        $cabeca = (string) file_get_contents($caminho, false, null, 0, 8);
        $tipo = (string) $arquivo->getMimeType();

        $valido = match ($extensao) {
            'pdf' => str_starts_with($cabeca, '%PDF-') && in_array($tipo, ['application/pdf', 'application/octet-stream'], true),
            'doc' => str_starts_with($cabeca, hex2bin('D0CF11E0A1B11AE1')) && in_array($tipo, [
                'application/msword',
                'application/CDFV2',
                'application/x-ole-storage',
                'application/vnd.ms-office',
                'application/octet-stream',
            ], true),
            'docx' => $this->docxValido($caminho, $tipo),
        };

        return $valido ? null : 'O conteúdo do arquivo não corresponde a um PDF ou Word.';
    }

    private function docxValido(string $caminho, string $tipo): bool
    {
        if (! in_array($tipo, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ], true)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($caminho) !== true) {
            return false;
        }

        $valido = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        return $valido;
    }

    private function agrupar($query, string $coluna): array
    {
        return $query
            ->selectRaw("{$coluna} as nome, count(*) as total")
            ->groupBy($coluna)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($linha) => [
                'nome' => $linha->nome ?: 'Sem informação',
                'total' => (int) $linha->total,
            ])
            ->all();
    }

    private function opcoes(): array
    {
        $eixos = Eixo::query()->where('ativo', true)->orderBy('nome')->pluck('nome')->all();
        $unidades = Unidade::query()->where('ativo', true)->orderBy('nome')->pluck('nome')->all();

        return [
            'tipos' => config('svt.tipos'),
            'etapas' => config('svt.etapas'),
            'ordem' => config('svt.ordem'),
            'responsaveis' => config('svt.responsaveis'),
            'eixos' => $eixos !== [] ? $eixos : array_keys(config('svt.responsaveis')),
            'transportes' => config('svt.transportes'),
            'unidades' => $unidades !== [] ? $unidades : config('svt.unidades'),
            'cota_observacao' => 'Critério provisório: visita realizada ou com transporte definido consome a cota. O cliente ainda não confirmou período, carga horária nem visitas canceladas.',
        ];
    }

    private function apresentar(VisitaTecnica $visita, Usuario $user): array
    {
        $dados = $visita->toArray();
        $dados['etapa_label'] = config("svt.etapas.{$visita->etapa}", $visita->status);
        $dados['tipo_label'] = config("svt.tipos.{$visita->tipo_curso}.label");
        $dados['pode_decidir'] = $this->fluxo->podeDecidir($visita, $user);
        $dados['pode_transporte'] = $this->fluxo->podeTransporte($visita, $user);
        $dados['pode_relatorio'] = $this->fluxo->podeRelatorio($visita, $user);
        $dados['pode_editar'] = $this->fluxo->podeEditar($visita, $user);
        $dados['pode_cancelar'] = $this->fluxo->podeCancelar($visita, $user);
        $dados['pode_realizar'] = $this->fluxo->podeRealizar($visita, $user);
        $dados['tem_relatorio'] = filled($visita->relatorio_arquivo);
        unset($dados['relatorio_arquivo']);

        return $dados;
    }

    private function descreverEvento(VisitaEvento $evento): string
    {
        $nova = config("svt.etapas.{$evento->etapa_nova}", $evento->etapa_nova);

        return match ($evento->evento) {
            'rascunho', 'rascunho_atualizado' => 'Rascunho salvo',
            'envio' => 'Enviado ao núcleo pedagógico',
            'aprovacao' => "Aprovado. Seguiu para {$nova}",
            'reprovacao' => "Reprovado. {$nova}",
            'transporte_definido' => 'Transporte definido',
            'transporte_atualizado' => 'Transporte atualizado',
            'realizacao' => 'Visita marcada como realizada',
            'relatorio' => 'Relatório entregue',
            'cancelamento' => 'Solicitação cancelada',
            'exemplo' => 'Registro de demonstração',
            default => $nova ?: $evento->evento,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function atividadeDaEquipe(): array
    {
        $visitas = VisitaTecnica::query()
            ->whereNotNull('etapa')
            ->orderByDesc('id')
            ->get(['id', 'etapa', 'status', 'turma', 'local_visita', 'criado_por', 'instrutor', 'unidade', 'responsavel_area']);

        return Usuario::query()
            ->with('eixos')
            ->where('status', true)
            ->orderBy('nome')
            ->get()
            ->map(function (Usuario $pessoa) use ($visitas) {
                $suas = $visitas
                    ->filter(fn (VisitaTecnica $visita) => $this->visitaCom($pessoa, $visita))
                    ->values();

                return [
                    'id' => $pessoa->id,
                    'nome' => $pessoa->nome,
                    'perfil' => $pessoa->perfil,
                    'unidade' => $pessoa->unidade,
                    'area' => $pessoa->area,
                    'foto' => $pessoa->foto,
                    'pendencias' => $suas->count(),
                    'fazendo' => $this->textoAtividade($pessoa, $suas->count()),
                    'itens' => $suas->take(3)->map(fn (VisitaTecnica $visita) => [
                        'id' => $visita->id,
                        'turma' => $visita->turma,
                        'local_visita' => $visita->local_visita,
                        'etapa_label' => config("svt.etapas.{$visita->etapa}", $visita->status),
                    ])->all(),
                ];
            })
            ->all();
    }

    private function visitaCom(Usuario $pessoa, VisitaTecnica $visita): bool
    {
        return match ($pessoa->perfil) {
            Usuario::PERFIL_INSTRUTOR => ! in_array($visita->etapa, ['recusada', 'realizada'], true)
                && (
                    (int) $visita->criado_por === (int) $pessoa->id
                    || mb_strtolower(trim((string) $visita->instrutor)) === mb_strtolower(trim($pessoa->nome))
                ),
            Usuario::PERFIL_NUCLEO => $visita->etapa === 'nucleo' && $visita->unidade === $pessoa->unidade,
            Usuario::PERFIL_CPED => $visita->etapa === 'cped' && in_array($visita->responsavel_area, $pessoa->areasResponsaveis(), true),
            Usuario::PERFIL_DIRECAO => $visita->etapa === 'direcao',
            Usuario::PERFIL_NULOG => $visita->etapa === 'nulog',
            default => false,
        };
    }

    private function textoAtividade(Usuario $pessoa, int $quantidade): string
    {
        if ($pessoa->isAdministrador()) {
            return 'Não analisa visita. Cuida de quem entra no sistema.';
        }

        if ($quantidade === 0) {
            return $pessoa->perfil === Usuario::PERFIL_INSTRUTOR
                ? 'Nenhuma visita em andamento.'
                : 'Nada parado com esta pessoa.';
        }

        $visitas = $quantidade === 1 ? '1 visita' : "{$quantidade} visitas";

        return match ($pessoa->perfil) {
            Usuario::PERFIL_INSTRUTOR => "Acompanha {$visitas} que pediu.",
            Usuario::PERFIL_NUCLEO => "{$visitas} da unidade {$pessoa->unidade} na análise do núcleo.",
            Usuario::PERFIL_CPED => "{$visitas} do eixo na área {$pessoa->area}.",
            Usuario::PERFIL_DIRECAO => "{$visitas} para a última análise.",
            Usuario::PERFIL_NULOG => "{$visitas} para registrar o transporte.",
            default => 'Sem etapa no fluxo da visita.',
        };
    }
}
