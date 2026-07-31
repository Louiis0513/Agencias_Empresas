<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerceroContacto extends Model
{
    protected $table = 'tercero_contactos';

    protected $fillable = [
        'tercero_id',
        'nombre',
        'cargo',
        'parentesco',
        'tipo_contacto',
        'email',
        'telefono',
        'celular',
        'es_principal',
        'es_facturacion',
        'es_cartera',
        'es_compras',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'es_facturacion' => 'boolean',
            'es_cartera' => 'boolean',
            'es_compras' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'tercero_id');
    }
}
