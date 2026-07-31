<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContratoLaboral extends Model
{
    protected $table = 'contratos_laborales';

    protected $fillable = [
        'tercero_id',
        'tipo_contrato',
        'fecha_inicio',
        'fecha_fin',
        'salario_base',
        'jornada',
        'cargo',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'salario_base' => 'decimal:2',
        ];
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'tercero_id');
    }
}
