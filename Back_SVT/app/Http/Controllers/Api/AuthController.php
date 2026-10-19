<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = Usuario::where('email', $request->email)->first();

        if (! $usuario || ! Hash::check($request->senha, $usuario->senha)) {
            $this->auditoria->registrar(
                categoria: 'autenticacao',
                acao: 'login',
                resultado: 'falha',
                usuario: $usuario,
                entidadeTipo: 'usuario',
                entidadeId: $usuario ? (string) $usuario->id : null,
                novo: ['email' => $request->email],
            );

            throw ValidationException::withMessages([
                'email' => ['E-mail ou senha inválidos.'],
            ]);
        }

        if (! $usuario->status) {
            $this->auditoria->registrar(
                categoria: 'autenticacao',
                acao: 'login',
                resultado: 'falha',
                usuario: $usuario,
                entidadeTipo: 'usuario',
                entidadeId: (string) $usuario->id,
                novo: ['email' => $usuario->email, 'motivo' => 'inativo'],
            );

            throw ValidationException::withMessages([
                'email' => ['Usuário inativo. Entre em contato com o administrador.'],
            ]);
        }

        $this->auditoria->registrar(
            categoria: 'autenticacao',
            acao: 'login',
            resultado: 'sucesso',
            usuario: $usuario,
            entidadeTipo: 'usuario',
            entidadeId: (string) $usuario->id,
            unidade: $usuario->unidade,
        );

        $token = $usuario->createToken('sgp-api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'usuario' => [
                'id' => $usuario->id,
                'nome' => $usuario->nome,
                'email' => $usuario->email,
                'perfil' => $usuario->perfil,
                'unidade' => $usuario->unidade,
                'area' => $usuario->area,
                'foto' => $usuario->foto,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();
        $this->auditoria->registrar(
            categoria: 'autenticacao',
            acao: 'logout',
            resultado: 'sucesso',
            usuario: $usuario,
            entidadeTipo: 'usuario',
            entidadeId: (string) $usuario->id,
            unidade: $usuario->unidade,
        );
        $usuario->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'usuario' => $request->user(),
        ]);
    }
}
