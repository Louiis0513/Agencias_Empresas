<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComprobanteIngresoDestino extends Model
{
    use HasFactory;

    protected $table = 'comprobante_ingreso_destinos';

    protected $fillable = [
        'comprobante_ingreso_id',
        'bolsillo_id',
        'amount',
        'reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function comprobanteIngreso()
    {
        return $this->belongsTo(ComprobanteIngreso::class);
    }

    public function bolsillo()
    {
        return $this->belongsTo(Bolsillo::class);
    }
}
