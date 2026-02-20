<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SesionCajaDetalle extends Model
{
    use HasFactory;

    protected $table = 'sesion_caja_detalles';

    protected $fillable = [
        'sesion_caja_id',
        'bolsillo_id',
        'saldo_esperado_apertura',
        'saldo_fisico_apertura',
        'saldo_esperado_cierre',
        'saldo_fisico_cierre',
    ];

    protected $casts = [
        'saldo_esperado_apertura' => 'decimal:2',
        'saldo_fisico_apertura' => 'decimal:2',
        'saldo_esperado_cierre' => 'decimal:2',
        'saldo_fisico_cierre' => 'decimal:2',
    ];

    public function sesionCaja()
    {
        return $this->belongsTo(SesionCaja::class, 'sesion_caja_id');
    }

    public function bolsillo()
    {
        return $this->belongsTo(Bolsillo::class);
    }
}
