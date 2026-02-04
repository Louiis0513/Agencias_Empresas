<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComprobanteIngresoAplicacion extends Model
{
    use HasFactory;

    protected $table = 'comprobante_ingreso_aplicaciones';

    protected $fillable = [
        'comprobante_ingreso_id',
        'account_receivable_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function comprobanteIngreso()
    {
        return $this->belongsTo(ComprobanteIngreso::class);
    }

    public function accountReceivable()
    {
        return $this->belongsTo(AccountReceivable::class);
    }
}
