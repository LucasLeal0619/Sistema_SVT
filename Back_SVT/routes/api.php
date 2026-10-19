<?php

use App\Http\Controllers\Api\AuditoriaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CadastroInstitucionalController;
use App\Http\Controllers\Api\SvtController;
use App\Http\Controllers\Api\UsuarioController;
use App\Models\Usuario;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'usuario.ativo'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    Route::middleware('perfil:'.Usuario::PERFIL_ADMINISTRADOR)
        ->apiResource('usuarios', UsuarioController::class);

    Route::prefix('svt')->group(function () {
        Route::get('catalogo', [SvtController::class, 'catalogo']);
        Route::get('cota', [SvtController::class, 'cota']);
        Route::get('turmas', [SvtController::class, 'turmas']);
        Route::get('turmas/{turma}/historico', [SvtController::class, 'historicoTurma'])
            ->middleware('perfil:'.Usuario::PERFIL_ADMINISTRADOR);
        Route::get('painel', [SvtController::class, 'painel']);
        Route::get('relatorios', [SvtController::class, 'relatorios']);
        Route::get('solicitacoes', [SvtController::class, 'index']);
        Route::post('solicitacoes', [SvtController::class, 'store']);
        Route::get('solicitacoes/{visitaTecnica}', [SvtController::class, 'show']);
        Route::put('solicitacoes/{visitaTecnica}', [SvtController::class, 'update']);
        Route::post('solicitacoes/{visitaTecnica}/cancelar', [SvtController::class, 'cancelar']);
        Route::post('solicitacoes/{visitaTecnica}/decisao', [SvtController::class, 'decidir']);
        Route::post('solicitacoes/{visitaTecnica}/transporte', [SvtController::class, 'transporte']);
        Route::post('solicitacoes/{visitaTecnica}/realizar', [SvtController::class, 'realizar']);
        Route::post('solicitacoes/{visitaTecnica}/relatorio', [SvtController::class, 'relatorio']);
        Route::get('solicitacoes/{visitaTecnica}/relatorio/arquivo', [SvtController::class, 'baixarRelatorio']);

        Route::middleware('perfil:'.Usuario::PERFIL_ADMINISTRADOR)->group(function () {
            Route::get('auditoria', [AuditoriaController::class, 'index']);
            Route::get('auditoria/{evento}', [AuditoriaController::class, 'show']);
        });

        Route::middleware('perfil:'.Usuario::PERFIL_ADMINISTRADOR)->prefix('cadastros')->group(function () {
            Route::get('{recurso}', [CadastroInstitucionalController::class, 'index']);
            Route::post('{recurso}', [CadastroInstitucionalController::class, 'store']);
            Route::put('{recurso}/{id}', [CadastroInstitucionalController::class, 'update']);
            Route::post('{recurso}/{id}/desativar', [CadastroInstitucionalController::class, 'desativar']);
        });
    });
});
