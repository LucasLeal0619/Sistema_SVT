<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitaTecnica extends Model
{
    protected $table = 'visita_tecnicas';

    protected $fillable = [
        'unidade',
        'eixo',
        'processo_sei',
        'data_solicitacao',
        'data_visita_prevista',
        'prazo_limite',
        'status',
        'responsavel',
        'relatorio',
        'observacao',
        'criado_por',
        'atualizado_por',
        'turma',
        'instrutor',
        'curso',
        'tipo_curso',
        'local_visita',
        'atividade',
        'justificativa',
        'etapa',
        'motivo_decisao',
        'responsavel_area',
        'acima_do_limite',
        'visitas_utilizadas',
        'limite',
        'transporte_tipo',
        'veiculo',
        'placa',
        'data_transporte',
        'horario_transporte',
        'motorista',
        'relatorio_arquivo',
        'horario_pretendido',
        'objetivo',
        'relacao_curricular',
        'horario_retorno',
        'info_transporte',
        'relatorio_texto',
        'realizada_em',
        'relatorio_entregue_em',
        'ficticio',
        'curso_id',
        'turma_id',
        'eixo_id',
        'unidade_id',
    ];

    protected function casts(): array
    {
        return [
            'data_solicitacao' => 'date:Y-m-d',
            'data_visita_prevista' => 'date:Y-m-d',
            'prazo_limite' => 'date:Y-m-d',
            'data_transporte' => 'date:Y-m-d',
            'acima_do_limite' => 'boolean',
            'ficticio' => 'boolean',
            'realizada_em' => 'datetime',
            'relatorio_entregue_em' => 'datetime',
        ];
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(VisitaEvento::class);
    }

    public function turmaVinculo(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_id');
    }
}
