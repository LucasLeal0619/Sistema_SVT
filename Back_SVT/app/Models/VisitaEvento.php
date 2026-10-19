<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitaEvento extends Model
{
    protected $fillable = [
        'visita_tecnica_id',
        'usuario_id',
        'perfil',
        'evento',
        'etapa_anterior',
        'etapa_nova',
        'texto',
    ];

    public function visita(): BelongsTo
    {
        return $this->belongsTo(VisitaTecnica::class, 'visita_tecnica_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
