<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use App\Services\AuditoriaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPerfil
{
    /**
     * @param  string  ...$perfis  Perfis autorizados (ex.: Administrador)
     */
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! in_array($usuario->perfil, $perfis, true)) {
            app(AuditoriaService::class)->registrar(
                categoria: 'seguranca',
                acao: 'acesso_negado',
                resultado: 'negado',
                usuario: $usuario instanceof Usuario ? $usuario : null,
                entidadeTipo: 'rota',
                entidadeId: mb_substr($request->path(), 0, 40),
                unidade: $usuario instanceof Usuario ? $usuario->unidade : null,
            );

            return response()->json([
                'message' => 'Acesso não autorizado para o seu perfil.',
            ], 403);
        }

        return $next($request);
    }
}
