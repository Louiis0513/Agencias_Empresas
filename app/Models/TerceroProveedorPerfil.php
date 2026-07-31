<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerceroProveedorPerfil extends Model
{
    protected $table = 'tercero_proveedor_perfiles';

    protected $fillable = [
        'tercero_id',
        'plazo_pago_dias',
        'preferido',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'preferido' => 'boolean',
        ];
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'tercero_id');
    }
}
