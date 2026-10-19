<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Eixo extends Model
{
    protected $fillable = ['nome', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    public function coordenadores(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'usuario_eixos');
    }
}
