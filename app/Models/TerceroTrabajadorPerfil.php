<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerceroTrabajadorPerfil extends Model
{
    protected $table = 'tercero_trabajador_perfiles';

    protected $fillable = [
        'tercero_id',
        'role_id',
        'cargo',
        'fecha_ingreso',
        'estado_laboral',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
        ];
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'tercero_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
