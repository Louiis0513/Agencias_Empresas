<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Unidad de medida DIAN para factura electrónica.
 * PK = codigo (sin id autoincrement).
 */
class UnidadMedidaFe extends Model
{
    protected $table = 'unidades_medida_fe';

    protected $primaryKey = 'codigo';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    public function etiqueta(): string
    {
        return $this->codigo.' - '.$this->nombre;
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('codigo');
    }
}
