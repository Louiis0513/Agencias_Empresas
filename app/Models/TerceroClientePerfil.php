<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TerceroClientePerfil extends Model
{
    protected $table = 'tercero_cliente_perfiles';

    protected $fillable = [
        'tercero_id',
        'credito_habilitado',
        'cupo_credito',
        'dias_plazo',
        'bloqueado_ventas',
        'motivo_bloqueo',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'credito_habilitado' => 'boolean',
            'cupo_credito' => 'decimal:2',
            'bloqueado_ventas' => 'boolean',
        ];
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'tercero_id');
    }

    public function gym(): HasOne
    {
        return $this->hasOne(TerceroClienteGymPerfil::class, 'tercero_cliente_perfil_id');
    }
}
