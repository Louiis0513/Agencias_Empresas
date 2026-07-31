<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerceroClienteGymPerfil extends Model
{
    protected $table = 'tercero_cliente_gym_perfiles';

    protected $fillable = [
        'tercero_cliente_perfil_id',
        'gender',
        'blood_type',
        'eps',
        'birth_date',
        'emergency_contact_name',
        'emergency_contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function perfilCliente(): BelongsTo
    {
        return $this->belongsTo(TerceroClientePerfil::class, 'tercero_cliente_perfil_id');
    }
}
