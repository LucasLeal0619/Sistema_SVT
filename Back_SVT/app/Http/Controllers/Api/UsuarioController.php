<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PaginatesIndex;
use App\Http\Requests\UsuarioRequest;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\UsuarioFotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    use PaginatesIndex;
    public function __construct(
        private UsuarioFotoService $fotos,
        private AuditoriaService $auditoria,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Usuario::query()->orderBy('nome');

        if ($request->filled('perfil')) {
            $query->where('perfil', $request->perfil);
        }

        if ($request->filled('unidade')) {
            $query->where('unidade', $request->unidade);
        }

        if ($request->filled('status')) {
            $query->where('status', filter_var($request->status, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('email', 'like', "%{$busca}%")
                    ->orWhere('telefone', 'like', "%{$busca}%")
                    ->orWhere('unidade', 'like', "%{$busca}%");
            });
        }

        $paginator = $this->paginar($query, $request);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => $this->metaPaginacao($paginator),
        ]);
    }

    public function store(UsuarioRequest $request): JsonResponse
    {
        $dados = $request->safe()->except(['foto', 'remover_foto']);
        $dados['senha'] = Hash::make($dados['senha']);
        $dados['status'] = $dados['status'] ?? true;

        if ($request->hasFile('foto')) {
            $dados['foto'] = $this->fotos->salvar($request->file('foto'));
        }

        $usuario = Usuario::create($dados);
        $usuario->sincronizarEixoPelaArea();
        $this->auditoria->registrar(
            categoria: 'usuarios',
            acao: 'usuario_criado',
            resultado: 'sucesso',
            usuario: $request->user(),
            entidadeTipo: 'usuario',
            entidadeId: (string) $usuario->id,
            unidade: $usuario->unidade,
            novo: $usuario->only(['nome', 'email', 'perfil', 'status', 'unidade', 'area', 'telefone']),
        );

        return response()->json([
            'message' => 'Usuário cadastrado com sucesso. Informe o e-mail e a senha ao colaborador.',
            'usuario' => $usuario->fresh(),
        ], 201);
    }

    public function show(Usuario $usuario): JsonResponse
    {
        return response()->json([
            'usuario' => $usuario,
        ]);
    }

    public function update(UsuarioRequest $request, Usuario $usuario): JsonResponse
    {
        $dados = $request->safe()->except(['foto', 'remover_foto']);

        if (! empty($dados['senha'])) {
            $dados['senha'] = Hash::make($dados['senha']);
        } else {
            unset($dados['senha']);
        }

        if (
            $usuario->isAdministrador()
            && isset($dados['perfil'])
            && $dados['perfil'] !== Usuario::PERFIL_ADMINISTRADOR
            && $this->ehUltimoAdministradorAtivo($usuario)
        ) {
            return response()->json([
                'message' => 'Não é possível alterar o perfil do último administrador ativo.',
            ], 422);
        }

        if (
            $usuario->isAdministrador()
            && array_key_exists('status', $dados)
            && $dados['status'] === false
            && $this->ehUltimoAdministradorAtivo($usuario)
        ) {
            return response()->json([
                'message' => 'Não é possível inativar o último administrador ativo.',
            ], 422);
        }

        $campos = ['nome', 'email', 'perfil', 'status', 'unidade', 'area', 'telefone'];
        $antes = $usuario->only($campos);
        $senhaTrocada = array_key_exists('senha', $dados);
        $fotoAnterior = $usuario->caminhoFoto();

        if ($request->boolean('remover_foto')) {
            $dados['foto'] = null;
            $this->fotos->apagar($fotoAnterior);
        } elseif ($request->hasFile('foto')) {
            $dados['foto'] = $this->fotos->salvar($request->file('foto'));
            $this->fotos->apagar($fotoAnterior);
        }

        $usuario->fill($dados);
        $alterados = array_keys($usuario->getDirty());
        $usuario->save();
        $usuario->sincronizarEixoPelaArea();

        $anterior = [];
        $novo = [];
        foreach ($campos as $campo) {
            if (! in_array($campo, $alterados, true)) {
                continue;
            }
            $anterior[$campo] = $antes[$campo] ?? null;
            $novo[$campo] = $usuario->{$campo};
        }
        if ($anterior !== [] || $novo !== []) {
            $this->auditoria->registrar(
                categoria: 'usuarios',
                acao: 'usuario_atualizado',
                resultado: 'sucesso',
                usuario: $request->user(),
                entidadeTipo: 'usuario',
                entidadeId: (string) $usuario->id,
                unidade: $usuario->unidade,
                anterior: $anterior,
                novo: $novo,
            );
        }
        if ($senhaTrocada) {
            $this->auditoria->registrar(
                categoria: 'usuarios',
                acao: 'senha_redefinida',
                resultado: 'sucesso',
                usuario: $request->user(),
                entidadeTipo: 'usuario',
                entidadeId: (string) $usuario->id,
                unidade: $usuario->unidade,
                novo: ['ocorrencia' => 'senha_redefinida'],
            );
        }

        // Inativação ou troca de senha invalida sessões abertas.
        if ((in_array('status', $alterados, true) && $usuario->status === false) || in_array('senha', $alterados, true)) {
            $usuario->tokens()->delete();
            $this->auditoria->registrar(
                categoria: 'usuarios',
                acao: 'tokens_revogados',
                resultado: 'sucesso',
                usuario: $request->user(),
                entidadeTipo: 'usuario',
                entidadeId: (string) $usuario->id,
                unidade: $usuario->unidade,
                novo: ['motivo' => $senhaTrocada ? 'senha_redefinida' : 'desativacao'],
            );
        }

        return response()->json([
            'message' => 'Usuário atualizado com sucesso.',
            'usuario' => $usuario->fresh(),
        ]);
    }

    public function destroy(Request $request, Usuario $usuario): JsonResponse
    {
        if ($request->user()->id === $usuario->id) {
            return response()->json([
                'message' => 'Você não pode excluir o próprio usuário.',
            ], 422);
        }

        if ($usuario->isAdministrador() && $this->ehUltimoAdministradorAtivo($usuario)) {
            return response()->json([
                'message' => 'Não é possível excluir o último administrador ativo.',
            ], 422);
        }

        $this->auditoria->registrar(
            categoria: 'usuarios',
            acao: 'usuario_excluido',
            resultado: 'sucesso',
            usuario: $request->user(),
            entidadeTipo: 'usuario',
            entidadeId: (string) $usuario->id,
            unidade: $usuario->unidade,
            anterior: $usuario->only(['nome', 'email', 'perfil', 'status', 'unidade', 'area']),
        );
        $this->fotos->apagar($usuario->caminhoFoto());
        $usuario->tokens()->delete();
        $usuario->delete();

        return response()->json([
            'message' => 'Usuário excluído com sucesso.',
        ]);
    }

    private function ehUltimoAdministradorAtivo(Usuario $usuario): bool
    {
        return Usuario::query()
            ->where('perfil', Usuario::PERFIL_ADMINISTRADOR)
            ->where('status', true)
            ->where('id', '!=', $usuario->id)
            ->doesntExist();
    }
}
