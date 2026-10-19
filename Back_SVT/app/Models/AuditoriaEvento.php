<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AuditoriaEvento extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auditoria_eventos';

    protected $fillable = [
        'ocorrido_em',
        'fuso',
        'usuario_id',
        'usuario_nome',
        'usuario_email',
        'perfil',
        'categoria',
        'acao',
        'entidade_tipo',
        'entidade_id',
        'unidade',
        'eixo',
        'resultado',
        'anterior',
        'novo',
        'correlacao',
        'ip',
        'agente',
        'hash_anterior',
        'hash',
    ];

    protected function casts(): array
    {
        return [
            'ocorrido_em' => 'datetime',
            'anterior' => 'array',
            'novo' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new RuntimeException('O registro de auditoria é imutável.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('O registro de auditoria não pode ser apagado por este caminho.');
    }
}
