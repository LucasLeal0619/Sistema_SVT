<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\Eixo;
use App\Models\RegraVisita;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CadastroInstitucionalController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function index(string $recurso): JsonResponse
    {
        $this->garantir($recurso);

        $dados = match ($recurso) {
            'eixos' => Eixo::query()->orderBy('nome')->get(),
            'unidades' => Unidade::query()->orderBy('nome')->get(),
            'cursos' => Curso::query()->with('eixo:id,nome')->orderBy('nome')->get(),
            'turmas' => Turma::query()->with(['curso.eixo:id,nome', 'unidade:id,nome', 'instrutor:id,nome'])->orderBy('nome')->get(),
            'regras' => RegraVisita::query()->with('curso:id,nome')->orderByDesc('id')->get(),
        };

        return response()->json(['data' => $dados]);
    }

    public function store(Request $request, string $recurso): JsonResponse
    {
        $this->garantir($recurso);
        $registro = match ($recurso) {
            'eixos' => Eixo::query()->create($this->validarEixo($request)),
            'unidades' => Unidade::query()->create($this->validarUnidade($request)),
            'cursos' => Curso::query()->create($this->validarCurso($request)),
            'turmas' => Turma::query()->create($this->validarTurma($request)),
            'regras' => RegraVisita::query()->create($this->validarRegra($request)),
        };
        $this->auditar($request, $recurso, $recurso.'_criado', $registro, null, $registro->only($registro->getFillable()));

        return response()->json([
            'message' => 'Cadastro salvo.',
            'registro' => $registro,
        ], 201);
    }

    public function update(Request $request, string $recurso, int $id): JsonResponse
    {
        $this->garantir($recurso);
        $registro = $this->encontrar($recurso, $id);
        $dados = match ($recurso) {
            'eixos' => $this->validarEixo($request, $id),
            'unidades' => $this->validarUnidade($request, $id),
            'cursos' => $this->validarCurso($request),
            'turmas' => $this->validarTurma($request),
            'regras' => $this->validarRegra($request),
        };
        $antes = $registro->only(array_keys($dados));
        $registro->fill($dados);
        $alterados = $registro->getDirty();
        $registro->save();
        $acao = match (true) {
            $recurso === 'regras' && array_key_exists('limite', $alterados) => 'limite_alterado',
            $recurso === 'regras' && (array_key_exists('vigencia_inicio', $alterados) || array_key_exists('vigencia_fim', $alterados)) => 'vigencia_alterada',
            $recurso === 'cursos' && array_key_exists('carga_horaria', $alterados) => 'carga_horaria_alterada',
            default => $recurso.'_atualizado',
        };
        $this->auditar($request, $recurso, $acao, $registro, array_intersect_key($antes, $alterados), $alterados);

        return response()->json([
            'message' => 'Cadastro atualizado.',
            'registro' => $registro->fresh(),
        ]);
    }

    public function desativar(string $recurso, int $id): JsonResponse
    {
        $this->garantir($recurso);
        $registro = $this->encontrar($recurso, $id);
        $registro->ativo = false;
        $registro->save();
        $this->auditar(request(), $recurso, $recurso.'_desativado', $registro, ['ativo' => true], ['ativo' => false]);

        return response()->json([
            'message' => 'Cadastro desativado. O histórico das visitas permanece.',
        ]);
    }

    private function auditar(Request $request, string $recurso, string $acao, Model $registro, ?array $anterior, ?array $novo): void
    {
        $ator = $request->user();
        $this->auditoria->registrar(
            categoria: 'configuracoes',
            acao: $acao,
            resultado: 'sucesso',
            usuario: $ator instanceof Usuario ? $ator : null,
            entidadeTipo: $recurso,
            entidadeId: (string) $registro->getKey(),
            unidade: $registro->getAttribute('nome') && $recurso === 'unidades' ? (string) $registro->getAttribute('nome') : null,
            eixo: $registro->getAttribute('nome') && $recurso === 'eixos' ? (string) $registro->getAttribute('nome') : null,
            anterior: $anterior,
            novo: $novo,
        );
    }

    private function garantir(string $recurso): void
    {
        if (! in_array($recurso, ['eixos', 'unidades', 'cursos', 'turmas', 'regras'], true)) {
            abort(404);
        }
    }

    private function encontrar(string $recurso, int $id): Eixo|Unidade|Curso|Turma|RegraVisita
    {
        $modelo = match ($recurso) {
            'eixos' => Eixo::class,
            'unidades' => Unidade::class,
            'cursos' => Curso::class,
            'turmas' => Turma::class,
            'regras' => RegraVisita::class,
        };

        return $modelo::query()->findOrFail($id);
    }

    private function validarEixo(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:120', Rule::unique('eixos', 'nome')->ignore($id)],
            'ativo' => ['sometimes', 'boolean'],
        ]);
    }

    private function validarUnidade(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:100', Rule::unique('unidades', 'nome')->ignore($id)],
            'ativo' => ['sometimes', 'boolean'],
        ]);
    }

    private function validarCurso(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:180'],
            'eixo_id' => ['required', 'integer', 'exists:eixos,id'],
            'tipo_curso' => ['required', 'string', Rule::in(array_keys(config('svt.tipos')))],
            'carga_horaria' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'ativo' => ['sometimes', 'boolean'],
        ]);
    }

    private function validarTurma(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'curso_id' => ['required', 'integer', 'exists:cursos,id'],
            'unidade_id' => ['required', 'integer', 'exists:unidades,id'],
            'instrutor_id' => ['nullable', 'integer', 'exists:usuarios,id'],
            'ativo' => ['sometimes', 'boolean'],
        ]);
    }

    private function validarRegra(Request $request): array
    {
        return $request->validate([
            'tipo_curso' => ['nullable', 'string', Rule::in(array_keys(config('svt.tipos')))],
            'curso_id' => ['nullable', 'integer', 'exists:cursos,id'],
            'carga_horaria_min' => ['nullable', 'integer', 'min:0'],
            'carga_horaria_max' => ['nullable', 'integer', 'min:0'],
            'limite' => ['required', 'integer', 'min:0', 'max:99'],
            'vigencia_inicio' => ['required', 'date'],
            'vigencia_fim' => ['nullable', 'date', 'after_or_equal:vigencia_inicio'],
            'criterio' => ['nullable', 'string', 'max:40'],
            'ativo' => ['sometimes', 'boolean'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
