<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\PaginatesIndex;
use App\Http\Controllers\Controller;
use App\Models\AuditoriaEvento;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    use PaginatesIndex;

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request);
        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:120'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            'perfil' => ['nullable', 'string', 'max:40'],
            'unidade' => ['nullable', 'string', 'max:120'],
            'eixo' => ['nullable', 'string', 'max:120'],
            'categoria' => ['nullable', 'string', 'max:40'],
            'acao' => ['nullable', 'string', 'max:60'],
            'resultado' => ['nullable', 'in:sucesso,falha,negado'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $consulta = $this->filtrar(AuditoriaEvento::query(), $filtros);

        $indicadores = [
            'total' => (clone $consulta)->count(),
            'sucesso' => (clone $consulta)->where('resultado', 'sucesso')->count(),
            'falha' => (clone $consulta)->where('resultado', 'falha')->count(),
            'negado' => (clone $consulta)->where('resultado', 'negado')->count(),
        ];

        $pagina = $this->paginar($consulta->orderByDesc('id'), $request, 15);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (AuditoriaEvento $evento) => $this->resumo($evento))->values(),
            'meta' => $this->metaPaginacao($pagina),
            'indicadores' => $indicadores,
        ]);
    }

    public function show(Request $request, AuditoriaEvento $evento): JsonResponse
    {
        $this->autorizar($request);

        return response()->json([
            'evento' => $this->detalhe($evento),
        ]);
    }

    private function autorizar(Request $request): void
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof Usuario && $usuario->isAdministrador(), 403, 'Acesso não autorizado para o seu perfil.');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function filtrar(Builder $consulta, array $filtros): Builder
    {
        if (! empty($filtros['busca'])) {
            $busca = str_replace(['%', '_'], ['\\%', '\\_'], $filtros['busca']);
            $consulta->where(function (Builder $filtro) use ($busca) {
                $filtro->where('usuario_nome', 'like', "%{$busca}%")
                    ->orWhere('usuario_email', 'like', "%{$busca}%")
                    ->orWhere('acao', 'like', "%{$busca}%")
                    ->orWhere('entidade_id', 'like', "%{$busca}%")
                    ->orWhere('correlacao', 'like', "%{$busca}%");
            });
        }

        if (! empty($filtros['de'])) {
            $consulta->whereDate('ocorrido_em', '>=', $filtros['de']);
        }
        if (! empty($filtros['ate'])) {
            $consulta->whereDate('ocorrido_em', '<=', $filtros['ate']);
        }
        foreach (['perfil', 'unidade', 'eixo', 'categoria', 'acao', 'resultado'] as $campo) {
            if (! empty($filtros[$campo])) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }

        return $consulta;
    }

    private function resumo(AuditoriaEvento $evento): array
    {
        return [
            'id' => $evento->id,
            'ocorrido_em' => $evento->ocorrido_em?->toIso8601String(),
            'fuso' => $evento->fuso,
            'usuario_id' => $evento->usuario_id,
            'usuario_nome' => $evento->usuario_nome,
            'perfil' => $evento->perfil,
            'categoria' => $evento->categoria,
            'acao' => $evento->acao,
            'entidade_tipo' => $evento->entidade_tipo,
            'entidade_id' => $evento->entidade_id,
            'unidade' => $evento->unidade,
            'eixo' => $evento->eixo,
            'resultado' => $evento->resultado,
        ];
    }

    private function detalhe(AuditoriaEvento $evento): array
    {
        return $this->resumo($evento) + [
            'usuario_email' => $evento->usuario_email,
            'anterior' => $evento->anterior,
            'novo' => $evento->novo,
            'correlacao' => $evento->correlacao,
            'ip' => $evento->ip,
            'agente' => $evento->agente,
        ];
    }
}
