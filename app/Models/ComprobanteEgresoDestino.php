<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComprobanteEgresoDestino extends Model
{
    use HasFactory;

    protected $table = 'comprobante_egreso_destinos';

    protected $fillable = [
        'comprobante_egreso_id',
        'type',
        'account_payable_id',
        'concepto',
        'beneficiario',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public const TYPE_CUENTA_POR_PAGAR = 'CUENTA_POR_PAGAR';
    public const TYPE_GASTO_DIRECTO = 'GASTO_DIRECTO';

    public function comprobanteEgreso()
    {
        return $this->belongsTo(ComprobanteEgreso::class, 'comprobante_egreso_id');
    }

    public function accountPayable()
    {
        return $this->belongsTo(AccountPayable::class);
    }

    public function isCuentaPorPagar(): bool
    {
        return $this->type === self::TYPE_CUENTA_POR_PAGAR;
    }

    public function isGastoDirecto(): bool
    {
        return $this->type === self::TYPE_GASTO_DIRECTO;
    }
}
