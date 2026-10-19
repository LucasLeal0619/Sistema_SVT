<?php

namespace App\Services;

use App\Models\AuditoriaEvento;
use App\Models\Usuario;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AuditoriaService
{
    /**
     * Grava um evento imutável. Falha de gravação interrompe a operação,
     * para que uma ação crítica não termine sem rastreio.
     *
     * @param  array<string, mixed>|null  $anterior
     * @param  array<string, mixed>|null  $novo
     */
    public function registrar(
        string $categoria,
        string $acao,
        string $resultado,
        ?Usuario $usuario = null,
        ?string $entidadeTipo = null,
        ?string $entidadeId = null,
        ?string $unidade = null,
        ?string $eixo = null,
        ?array $anterior = null,
        ?array $novo = null,
    ): AuditoriaEvento {
        if (! in_array($resultado, ['sucesso', 'falha', 'negado'], true)) {
            throw new RuntimeException('Resultado de auditoria desconhecido.');
        }

        try {
            $evento = DB::transaction(function () use ($categoria, $acao, $resultado, $usuario, $entidadeTipo, $entidadeId, $unidade, $eixo, $anterior, $novo) {
                $this->travarCadeia();
                $ultimo = AuditoriaEvento::query()->orderByDesc('id')->lockForUpdate()->first();
                $hashAnterior = $ultimo?->hash ?: str_repeat('0', 64);
                $ocorrido = now();
                $fuso = (string) config('app.timezone', 'UTC');
                $anteriorLimpo = $this->limpar($anterior);
                $novoLimpo = $this->limpar($novo);
                $canonico = $this->canonico(
                    $hashAnterior,
                    $ocorrido->toIso8601String(),
                    $usuario?->id,
                    $categoria,
                    $acao,
                    $resultado,
                    $entidadeTipo,
                    $entidadeId,
                    $anteriorLimpo,
                    $novoLimpo,
                );

                return AuditoriaEvento::query()->create([
                    'ocorrido_em' => $ocorrido,
                    'fuso' => $fuso,
                    'usuario_id' => $usuario?->id,
                    'usuario_nome' => $usuario?->nome,
                    'usuario_email' => $usuario?->email,
                    'perfil' => $usuario?->perfil,
                    'categoria' => $categoria,
                    'acao' => $acao,
                    'entidade_tipo' => $entidadeTipo,
                    'entidade_id' => $entidadeId,
                    'unidade' => $unidade,
                    'eixo' => $eixo,
                    'resultado' => $resultado,
                    'anterior' => $anteriorLimpo,
                    'novo' => $novoLimpo,
                    'correlacao' => $this->correlacao(),
                    'ip' => $this->ip(),
                    'agente' => $this->agente(),
                    'hash_anterior' => $hashAnterior,
                    'hash' => hash('sha256', $canonico),
                ]);
            });

            return $evento;
        } catch (Throwable $falha) {
            if ($falha instanceof RuntimeException && $falha->getMessage() === 'Resultado de auditoria desconhecido.') {
                throw $falha;
            }

            throw new RuntimeException('Não foi possível registrar a auditoria.', 0, $falha);
        } finally {
            $this->soltarCadeia();
        }
    }

    public function cadeiaIntegra(): bool
    {
        $anterior = str_repeat('0', 64);
        foreach (AuditoriaEvento::query()->orderBy('id')->get() as $evento) {
            $calculado = hash('sha256', $this->canonico(
                $evento->hash_anterior,
                $evento->ocorrido_em?->toIso8601String() ?? '',
                $evento->usuario_id,
                $evento->categoria,
                $evento->acao,
                $evento->resultado,
                $evento->entidade_tipo,
                $evento->entidade_id,
                $evento->anterior,
                $evento->novo,
            ));
            if ($evento->hash_anterior !== $anterior || $calculado !== $evento->hash) {
                return false;
            }
            $anterior = $evento->hash;
        }

        return true;
    }

    private function travarCadeia(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $trava = DB::select('SELECT GET_LOCK(?, 5) as ok', ['svt_auditoria']);
        if ((int) ($trava[0]->ok ?? 0) !== 1) {
            throw new RuntimeException('Não foi possível registrar a auditoria.');
        }
    }

    private function soltarCadeia(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::select('SELECT RELEASE_LOCK(?)', ['svt_auditoria']);
    }

    private function correlacao(): string
    {
        $requisicao = request();
        if ($requisicao === null) {
            return (string) Str::uuid();
        }

        $atual = $requisicao->attributes->get('auditoria_correlacao');
        if (is_string($atual) && $atual !== '') {
            return $atual;
        }

        $novo = (string) Str::uuid();
        $requisicao->attributes->set('auditoria_correlacao', $novo);

        return $novo;
    }

    private function ip(): ?string
    {
        $ip = request()?->ip();
        if (! is_string($ip) || $ip === '') {
            return null;
        }

        return mb_substr($ip, 0, 45);
    }

    private function agente(): ?string
    {
        $agente = request()?->userAgent();
        if (! is_string($agente) || $agente === '') {
            return null;
        }

        return mb_substr($agente, 0, 160);
    }

    /**
     * @param  array<string, mixed>|null  $dados
     * @return array<string, mixed>|null
     */
    private function canonico(
        string $hashAnterior,
        string $ocorrido,
        mixed $usuarioId,
        string $categoria,
        string $acao,
        string $resultado,
        ?string $entidadeTipo,
        ?string $entidadeId,
        ?array $anterior,
        ?array $novo,
    ): string {
        return implode('|', [
            $hashAnterior,
            $ocorrido,
            (string) ($usuarioId ?? ''),
            $categoria,
            $acao,
            $resultado,
            (string) $entidadeTipo,
            (string) $entidadeId,
            json_encode($this->ordenar($anterior), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            json_encode($this->ordenar($novo), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }

    private function ordenar(?array $dados): ?array
    {
        if ($dados === null) {
            return null;
        }

        ksort($dados);
        foreach ($dados as $chave => $valor) {
            if (is_array($valor)) {
                $dados[$chave] = $this->ordenar($valor);
            }
        }

        return $dados;
    }

    private function limpar(?array $dados): ?array
    {
        if ($dados === null) {
            return null;
        }

        $limpo = [];
        foreach ($dados as $chave => $valor) {
            $nome = mb_strtolower((string) $chave);
            if (in_array($nome, ['senha', 'password', 'token', 'remember_token', 'secret', 'cookie', 'authorization', 'api_key', 'bearer', 'relatorio_arquivo'], true)) {
                $limpo[$chave] = '[omitido]';
                continue;
            }
            if ($nome === 'cpf') {
                $limpo[$chave] = '***';
                continue;
            }
            if (is_array($valor)) {
                $limpo[$chave] = $this->limpar($valor);
                continue;
            }
            if ($valor instanceof DateTimeInterface) {
                $limpo[$chave] = $valor->format('Y-m-d H:i:s');
                continue;
            }
            if (is_string($valor) && mb_strlen($valor) > 500) {
                $limpo[$chave] = mb_substr($valor, 0, 500);
                continue;
            }
            $limpo[$chave] = $valor;
        }

        return $limpo;
    }
}
