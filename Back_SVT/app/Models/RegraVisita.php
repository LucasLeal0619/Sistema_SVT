<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegraVisita extends Model
{
    protected $table = 'regras_visita';

    protected $fillable = [
        'tipo_curso',
        'curso_id',
        'carga_horaria_min',
        'carga_horaria_max',
        'limite',
        'vigencia_inicio',
        'vigencia_fim',
        'criterio',
        'ativo',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'vigencia_inicio' => 'date:Y-m-d',
            'vigencia_fim' => 'date:Y-m-d',
        ];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
