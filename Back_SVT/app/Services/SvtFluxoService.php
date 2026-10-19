<?php

namespace App\Services;

use App\Models\RegraVisita;
use App\Models\Turma;
use App\Models\Usuario;
use App\Models\VisitaEvento;
use App\Models\VisitaTecnica;
use App\Services\AuditoriaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SvtFluxoService
{
    public function quadro(Turma $turma, ?int $ignorarId = null): array
    {
        $turma->loadMissing('curso.eixo', 'unidade', 'instrutor');
        $curso = $turma->curso;
        $regra = $this->regraVigente($curso->tipo_curso, $curso->id, $curso->carga_horaria);
        $limite = $regra?->limite ?? (int) config("svt.tipos.{$curso->tipo_curso}.limite", 0);

        $base = VisitaTecnica::query()
            ->where(function (Builder $query) use ($turma) {
                $query->where('turma_id', $turma->id)
                    ->orWhere(function (Builder $legado) use ($turma) {
                        $legado->whereNull('turma_id')
                            ->where('turma', $turma->nome)
                            ->where('curso', $turma->curso->nome);
                    });
            })
            ->when($ignorarId, fn (Builder $query) => $query->where('id', '!=', $ignorarId));

        $realizadas = (clone $base)->whereIn('etapa', ['realizada', 'relatorio'])->count();
        $programadas = (clone $base)->where('etapa', 'aprovada')->count();
        $pendentes = (clone $base)->whereIn('etapa', config('svt.cota.pendentes'))->count();
        $consumidas = $realizadas + $programadas;

        return [
            'limite' => $limite,
            'realizadas' => $realizadas,
            'programadas' => $programadas,
            'pendentes' => $pendentes,
            'consumidas' => $consumidas,
            'disponiveis' => max(0, $limite - $consumidas),
            'utilizadas' => $consumidas,
            'restantes' => max(0, $limite - $consumidas),
            'acima_do_limite' => $limite > 0 && $consumidas >= $limite,
            'criterio' => $regra?->criterio ?? config('svt.cota.criterio'),
            'observacao' => $regra?->observacao
                ?: 'Critério provisório: visita realizada ou com transporte definido consome a cota. Pendências, recusas e cancelamentos não consomem. O cliente ainda não confirmou o período nem o efeito da carga horária.',
        ];
    }

    public function podeCriar(Usuario $user): bool
    {
        return $user->perfil === Usuario::PERFIL_INSTRUTOR;
    }

    public function solicitar(array $dados, Usuario $user): VisitaTecnica
    {
        return $this->persistir($dados, $user, null, true);
    }

    public function guardarRascunho(array $dados, Usuario $user, ?VisitaTecnica $visita = null): VisitaTecnica
    {
        return $this->persistir($dados, $user, $visita, false);
    }

    public function atualizarRascunho(VisitaTecnica $visita, array $dados, Usuario $user, bool $enviar): VisitaTecnica
    {
        if (! $this->podeEditar($visita, $user)) {
            throw ValidationException::withMessages([
                'turma_id' => 'Só o instrutor edita o próprio rascunho.',
            ]);
        }

        return $this->persistir($dados, $user, $visita, $enviar);
    }

    public function podeEditar(VisitaTecnica $visita, Usuario $user): bool
    {
        return $visita->etapa === 'rascunho'
            && (int) $visita->criado_por === (int) $user->id;
    }

    public function podeCancelar(VisitaTecnica $visita, Usuario $user): bool
    {
        return (int) $visita->criado_por === (int) $user->id
            && in_array($visita->etapa, ['rascunho', 'nucleo'], true);
    }

    public function cancelar(VisitaTecnica $visita, Usuario $user, ?string $motivo): VisitaTecnica
    {
        if (! $this->podeCancelar($visita, $user)) {
            throw ValidationException::withMessages([
                'cancelamento' => 'Esta solicitação não pode mais ser cancelada por você.',
            ]);
        }

        return $this->transicionar($visita, $user, 'cancelada', 'Cancelada', 'cancelamento', $motivo);
    }

    public function podeDecidir(VisitaTecnica $visita, Usuario $user): bool
    {
        if (! in_array($visita->etapa, ['nucleo', 'cped', 'direcao'], true)) {
            return false;
        }

        return match ($visita->etapa) {
            'nucleo' => $user->perfil === Usuario::PERFIL_NUCLEO
                && $user->unidade
                && $user->unidade === $visita->unidade,
            'cped' => $user->perfil === Usuario::PERFIL_CPED
                && in_array($visita->responsavel_area, $user->areasResponsaveis(), true),
            'direcao' => $user->perfil === Usuario::PERFIL_DIRECAO,
            default => false,
        };
    }

    public function decidir(VisitaTecnica $visita, Usuario $user, bool $aprovar, ?string $motivo): VisitaTecnica
    {
        return DB::transaction(function () use ($visita, $user, $aprovar, $motivo) {
            $atual = VisitaTecnica::query()->whereKey($visita->id)->lockForUpdate()->firstOrFail();

            if (! $this->podeDecidir($atual, $user)) {
                throw ValidationException::withMessages([
                    'decisao' => 'Esta solicitação não está na sua etapa.',
                ]);
            }

            if (! $aprovar && blank($motivo)) {
                throw ValidationException::withMessages([
                    'motivo' => 'Informe o motivo da recusa.',
                ]);
            }

            if ($aprovar) {
                $etapa = match ($atual->etapa) {
                    'nucleo' => 'cped',
                    'cped' => 'direcao',
                    'direcao' => 'nulog',
                    default => $atual->etapa,
                };
                $status = $etapa === 'nulog' ? 'Aguardando Logística' : config("svt.etapas.{$etapa}");
                $evento = 'aprovacao';
            } else {
                $etapa = match ($atual->etapa) {
                    'nucleo' => 'recusada_nucleo',
                    'cped' => 'recusada_cped',
                    'direcao' => 'recusada_direcao',
                    default => 'recusada',
                };
                $status = config("svt.etapas.{$etapa}");
                $evento = 'reprovacao';
            }

            return $this->transicionar($atual, $user, $etapa, $status, $evento, $motivo);
        });
    }

    public function podeTransporte(VisitaTecnica $visita, Usuario $user): bool
    {
        return $user->perfil === Usuario::PERFIL_NULOG
            && in_array($visita->etapa, ['nulog', 'aprovada'], true);
    }

    public function registrarTransporte(VisitaTecnica $visita, Usuario $user, array $dados): VisitaTecnica
    {
        return DB::transaction(function () use ($visita, $user, $dados) {
            $atual = VisitaTecnica::query()->whereKey($visita->id)->lockForUpdate()->firstOrFail();

            if (! $this->podeTransporte($atual, $user)) {
                throw ValidationException::withMessages([
                    'transporte' => 'O transporte só é registrado pelo NULOG, depois da aprovação da direção.',
                ]);
            }

            $anterior = $atual->etapa;
            $camposLogistica = ['transporte_tipo', 'veiculo', 'placa', 'data_transporte', 'horario_transporte', 'horario_retorno', 'motorista'];
            $antesLogistica = $atual->only($camposLogistica);
            $atual->fill([
                'transporte_tipo' => $dados['transporte_tipo'],
                'veiculo' => $dados['veiculo'],
                'placa' => $dados['placa'],
                'data_transporte' => $dados['data_transporte'],
                'horario_transporte' => $dados['horario_transporte'],
                'horario_retorno' => $dados['horario_retorno'] ?? null,
                'motorista' => $dados['motorista'],
                'info_transporte' => $dados['info_transporte'] ?? null,
                'etapa' => 'aprovada',
                'status' => 'Transporte definido',
            ]);
            $antesAlterado = [];
            $depoisAlterado = [];
            foreach ($atual->getDirty() as $campo => $valor) {
                if (! in_array($campo, $camposLogistica, true)) {
                    continue;
                }
                $antesAlterado[$campo] = $antesLogistica[$campo] ?? null;
                $depoisAlterado[$campo] = $valor;
            }
            $atual->save();
            $this->registrarEvento(
                $atual,
                $user,
                $anterior === 'aprovada' ? 'transporte_atualizado' : 'transporte_definido',
                $anterior,
                'aprovada',
                $dados['info_transporte'] ?? null,
                ['anterior' => $antesAlterado, 'novo' => $depoisAlterado]
            );

            return $atual->fresh();
        });
    }

    public function podeRealizar(VisitaTecnica $visita, Usuario $user): bool
    {
        return $visita->etapa === 'aprovada' && $this->ehInstrutorDaVisita($visita, $user);
    }

    public function marcarRealizada(VisitaTecnica $visita, Usuario $user): VisitaTecnica
    {
        if (! $this->podeRealizar($visita, $user)) {
            throw ValidationException::withMessages([
                'realizacao' => 'A visita só é marcada como realizada pelo instrutor, depois do transporte definido.',
            ]);
        }

        $atual = $this->transicionar($visita, $user, 'realizada', 'Realizada', 'realizacao', null);
        $atual->realizada_em = now();
        $atual->save();

        return $atual->fresh();
    }

    public function podeRelatorio(VisitaTecnica $visita, Usuario $user): bool
    {
        return in_array($visita->etapa, ['realizada', 'relatorio'], true)
            && $this->ehInstrutorDaVisita($visita, $user);
    }

    public function anexarRelatorio(VisitaTecnica $visita, Usuario $user, ?string $texto, ?string $caminho): VisitaTecnica
    {
        if (! $this->podeRelatorio($visita, $user)) {
            throw ValidationException::withMessages([
                'arquivo' => 'O relatório é anexado pelo instrutor depois que a visita foi realizada.',
            ]);
        }

        $anterior = $visita->etapa;
        $jaTinhaArquivo = filled($visita->relatorio_arquivo);
        $visita->relatorio_texto = $texto ?: $visita->relatorio_texto;
        $visita->relatorio = 'Relatório anexado';
        if ($caminho) {
            $visita->relatorio_arquivo = $caminho;
        }
        $visita->etapa = 'relatorio';
        $visita->status = 'Relatório entregue';
        $visita->relatorio_entregue_em = now();

        return DB::transaction(function () use ($visita, $user, $anterior, $caminho, $jaTinhaArquivo) {
            $visita->save();
            $this->registrarEvento($visita, $user, 'relatorio', $anterior, 'relatorio', null, [
                'novo' => [
                    'arquivo_enviado' => filled($caminho),
                    'substituicao' => $jaTinhaArquivo && filled($caminho),
                    'extensao' => filled($caminho) ? strtolower(pathinfo($caminho, PATHINFO_EXTENSION)) : null,
                ],
            ]);

            return $visita->fresh();
        });
    }

    public function podeConsultar(VisitaTecnica $visita, Usuario $user): bool
    {
        return match ($user->perfil) {
            Usuario::PERFIL_INSTRUTOR => $this->ehInstrutorDaVisita($visita, $user),
            Usuario::PERFIL_NUCLEO => $user->unidade && $visita->unidade === $user->unidade,
            Usuario::PERFIL_CPED => in_array($visita->responsavel_area, $user->areasResponsaveis(), true)
                || ($visita->eixo_id && $user->eixos()->whereKey($visita->eixo_id)->exists()),
            Usuario::PERFIL_NULOG => in_array($visita->etapa, ['nulog', 'aprovada', 'realizada', 'relatorio'], true),
            default => true,
        };
    }

    public function restringirConsulta(Builder $query, Usuario $user): void
    {
        match ($user->perfil) {
            Usuario::PERFIL_INSTRUTOR => $query->where(function (Builder $filtro) use ($user) {
                $filtro->where('criado_por', $user->id)
                    ->orWhereIn('turma_id', function ($sub) use ($user) {
                        $sub->select('id')->from('turmas')->where('instrutor_id', $user->id);
                    })
                    ->orWhere(function (Builder $legado) use ($user) {
                        $legado->whereNull('turma_id')
                            ->whereRaw('LOWER(TRIM(instrutor)) = ?', [mb_strtolower(trim($user->nome))]);
                    });
            }),
            Usuario::PERFIL_NUCLEO => $query->where('unidade', $user->unidade ?: '__sem_unidade__'),
            Usuario::PERFIL_CPED => $query->where(function (Builder $filtro) use ($user) {
                $areas = $user->areasResponsaveis();
                $filtro->whereIn('responsavel_area', $areas !== [] ? $areas : ['__nenhuma__']);
                $ids = $user->eixos()->pluck('eixos.id');
                if ($ids->isNotEmpty()) {
                    $filtro->orWhereIn('eixo_id', $ids);
                }
            }),
            Usuario::PERFIL_NULOG => $query->whereIn('etapa', ['nulog', 'aprovada', 'realizada', 'relatorio']),
            default => null,
        };
    }

    public function aplicarFila(Builder $query, Usuario $user): void
    {
        if ($user->isAdministrador()) {
            $query->whereRaw('0 = 1');

            return;
        }

        match ($user->perfil) {
            Usuario::PERFIL_NUCLEO => $query->where('etapa', 'nucleo')->where('unidade', $user->unidade),
            Usuario::PERFIL_CPED => $query->where('etapa', 'cped')->where(function (Builder $filtro) use ($user) {
                $areas = $user->areasResponsaveis();
                $filtro->whereIn('responsavel_area', $areas !== [] ? $areas : ['__nenhuma__']);
            }),
            Usuario::PERFIL_DIRECAO => $query->where('etapa', 'direcao'),
            Usuario::PERFIL_NULOG => $query->where('etapa', 'nulog'),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function persistir(array $dados, Usuario $user, ?VisitaTecnica $visita, bool $enviar): VisitaTecnica
    {
        if (! $this->podeCriar($user)) {
            throw ValidationException::withMessages([
                'turma_id' => 'Seu perfil não abre solicitação de visita técnica.',
            ]);
        }

        $turma = Turma::query()->with('curso.eixo', 'unidade', 'instrutor')->find($dados['turma_id'] ?? null);
        if (! $turma || ! $turma->ativo || ! $turma->curso?->ativo) {
            throw ValidationException::withMessages([
                'turma_id' => 'Selecione uma turma ativa.',
            ]);
        }

        if ((int) $turma->instrutor_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'turma_id' => 'Você só solicita visita para turma vinculada a você.',
            ]);
        }

        $cota = $this->quadro($turma, $visita?->id);
        if ($enviar && $cota['acima_do_limite'] && blank($dados['justificativa'] ?? null)) {
            throw ValidationException::withMessages([
                'justificativa' => 'Esta turma já usou o limite de visitas. Explique por que esta visita extra é necessária.',
            ]);
        }

        $eixo = $turma->curso->eixo;
        $anterior = $visita?->etapa;
        $etapa = $enviar ? 'nucleo' : 'rascunho';
        $registro = $visita ?? new VisitaTecnica;
        $registro->fill([
            'unidade' => $turma->unidade->nome,
            'unidade_id' => $turma->unidade_id,
            'turma' => $turma->nome,
            'turma_id' => $turma->id,
            'instrutor' => $user->nome,
            'eixo' => $eixo?->nome,
            'eixo_id' => $eixo?->id,
            'curso' => $turma->curso->nome,
            'curso_id' => $turma->curso_id,
            'tipo_curso' => $turma->curso->tipo_curso,
            'local_visita' => $dados['local_visita'] ?? null,
            'data_solicitacao' => $registro->data_solicitacao ?? now()->toDateString(),
            'data_visita_prevista' => $dados['data_visita_prevista'] ?? null,
            'horario_pretendido' => $dados['horario_pretendido'] ?? null,
            'atividade' => $dados['atividade'] ?? null,
            'objetivo' => $dados['objetivo'] ?? null,
            'relacao_curricular' => $dados['relacao_curricular'] ?? null,
            'justificativa' => $cota['acima_do_limite'] ? ($dados['justificativa'] ?? null) : null,
            'observacao' => $dados['atividade'] ?? null,
            'status' => config("svt.etapas.{$etapa}"),
            'etapa' => $etapa,
            'responsavel' => $user->nome,
            'responsavel_area' => config('svt.responsaveis.'.($eixo?->nome ?? '')),
            'acima_do_limite' => $enviar && $cota['acima_do_limite'],
            'visitas_utilizadas' => $cota['consumidas'],
            'limite' => $cota['limite'],
            'criado_por' => $registro->criado_por ?: $user->id,
            'atualizado_por' => $user->id,
        ]);
        return DB::transaction(function () use ($registro, $user, $anterior, $enviar, $etapa, $dados) {
            $registro->save();

            $this->registrarEvento(
                $registro,
                $user,
                $anterior === null ? ($enviar ? 'envio' : 'rascunho') : ($enviar ? 'envio' : 'rascunho_atualizado'),
                $anterior,
                $etapa,
                $dados['justificativa'] ?? null
            );

            return $registro->fresh();
        });
    }

    private function transicionar(
        VisitaTecnica $visita,
        Usuario $user,
        string $etapa,
        string $status,
        string $evento,
        ?string $texto
    ): VisitaTecnica {
        $anterior = $visita->etapa;
        $visita->etapa = $etapa;
        $visita->status = $status;
        $visita->motivo_decisao = $texto;
        $visita->atualizado_por = $user->id;
        return DB::transaction(function () use ($visita, $user, $anterior, $etapa, $evento, $texto) {
            $visita->save();
            $this->registrarEvento($visita, $user, $evento, $anterior, $etapa, $texto);

            return $visita->fresh();
        });
    }

    /**
     * @param  array{anterior?: array<string, mixed>, novo?: array<string, mixed>}  $detalhe
     */
    private function registrarEvento(
        VisitaTecnica $visita,
        Usuario $user,
        string $evento,
        ?string $anterior,
        ?string $nova,
        ?string $texto,
        array $detalhe = []
    ): void {
        VisitaEvento::query()->create([
            'visita_tecnica_id' => $visita->id,
            'usuario_id' => $user->id,
            'perfil' => $user->perfil,
            'evento' => $evento,
            'etapa_anterior' => $anterior,
            'etapa_nova' => $nova,
            'texto' => $texto,
        ]);

        $categoria = match (true) {
            str_starts_with($evento, 'transporte') => 'logistica',
            $evento === 'relatorio' => 'documentos',
            default => 'visitas',
        };
        $novo = array_filter([
            'etapa' => $nova,
            'justificativa' => filled($texto) ? mb_substr((string) $texto, 0, 500) : null,
            'extraordinaria' => $visita->acima_do_limite ? true : null,
        ], fn ($valor) => $valor !== null && $valor !== '');

        app(AuditoriaService::class)->registrar(
            categoria: $categoria,
            acao: $evento,
            resultado: 'sucesso',
            usuario: $user,
            entidadeTipo: 'visita_tecnica',
            entidadeId: (string) $visita->id,
            unidade: $visita->unidade,
            eixo: $visita->eixo,
            anterior: array_merge(array_filter(['etapa' => $anterior], fn ($valor) => $valor !== null), $detalhe['anterior'] ?? []) ?: null,
            novo: array_merge($novo, $detalhe['novo'] ?? []) ?: null,
        );
    }

    private function ehInstrutorDaVisita(VisitaTecnica $visita, Usuario $user): bool
    {
        if ($user->perfil !== Usuario::PERFIL_INSTRUTOR) {
            return false;
        }

        if ((int) $visita->criado_por === (int) $user->id) {
            return true;
        }

        if ($visita->turma_id) {
            return Turma::query()
                ->whereKey($visita->turma_id)
                ->where('instrutor_id', $user->id)
                ->exists();
        }

        return mb_strtolower(trim((string) $visita->instrutor)) === mb_strtolower(trim($user->nome));
    }

    private function regraVigente(string $tipoCurso, int $cursoId, ?int $cargaHoraria): ?RegraVisita
    {
        $hoje = now()->toDateString();
        $candidatas = RegraVisita::query()
            ->where('ativo', true)
            ->whereDate('vigencia_inicio', '<=', $hoje)
            ->where(function (Builder $query) use ($hoje) {
                $query->whereNull('vigencia_fim')->orWhereDate('vigencia_fim', '>=', $hoje);
            })
            ->where(function (Builder $query) use ($tipoCurso, $cursoId) {
                $query->where('curso_id', $cursoId)->orWhere(function (Builder $tipo) use ($tipoCurso) {
                    $tipo->whereNull('curso_id')->where('tipo_curso', $tipoCurso);
                });
            })
            ->get();

        return $candidatas
            ->filter(function (RegraVisita $regra) use ($cargaHoraria) {
                if ($regra->carga_horaria_min === null && $regra->carga_horaria_max === null) {
                    return true;
                }
                if ($cargaHoraria === null) {
                    return false;
                }

                $minima = $regra->carga_horaria_min ?? 0;
                $maxima = $regra->carga_horaria_max ?? PHP_INT_MAX;

                return $cargaHoraria >= $minima && $cargaHoraria <= $maxima;
            })
            ->sortBy(fn (RegraVisita $regra) => [
                $regra->curso_id ? 0 : 1,
                ($regra->carga_horaria_min === null && $regra->carga_horaria_max === null) ? 1 : 0,
                ($regra->carga_horaria_max ?? PHP_INT_MAX) - ($regra->carga_horaria_min ?? 0),
                $regra->id,
            ])
            ->first();
    }
}
