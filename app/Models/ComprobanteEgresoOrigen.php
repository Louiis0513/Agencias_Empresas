<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComprobanteEgresoOrigen extends Model
{
    use HasFactory;

    protected $table = 'comprobante_egreso_origenes';

    protected $fillable = [
        'comprobante_egreso_id',
        'bolsillo_id',
        'amount',
        'reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function comprobanteEgreso()
    {
        return $this->belongsTo(ComprobanteEgreso::class, 'comprobante_egreso_id');
    }

    public function bolsillo()
    {
        return $this->belongsTo(Bolsillo::class);
    }
}
