<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curso extends Model
{
    protected $fillable = ['eixo_id', 'nome', 'tipo_curso', 'carga_horaria', 'ativo'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'carga_horaria' => 'integer',
        ];
    }

    public function eixo(): BelongsTo
    {
        return $this->belongsTo(Eixo::class);
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }
}
