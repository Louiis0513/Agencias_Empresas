<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ComprobanteIngreso extends Model
{
    use HasFactory;

    protected $table = 'comprobantes_ingreso';

    protected $fillable = [
        'store_id',
        'number',
        'total_amount',
        'monto_anticipo',
        'date',
        'notes',
        'type',
        'tipo_comprobante_id',
        'modo',
        'forma_pago_id',
        'centro_costo_id',
        'tercero_id',
        'invoice_id',
        'user_id',
        'reversed_at',
        'reversal_user_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'monto_anticipo' => 'decimal:2',
        'date' => 'date',
        'reversed_at' => 'datetime',
    ];

    public const TYPE_INGRESO_MANUAL = 'INGRESO_MANUAL';

    public const TYPE_COBRO_CUENTA = 'COBRO_CUENTA';

    public const TYPE_PAGO_FACTURA = 'PAGO_FACTURA';

    public const TYPE_ANTICIPO = 'ANTICIPO';

    public const MODO_ABONO = 'abono';

    public const MODO_ANTICIPO = 'anticipo';

    public const MODO_OTRO_INGRESO = 'otro_ingreso';

    public const MODOS = [
        self::MODO_ABONO,
        self::MODO_ANTICIPO,
        self::MODO_OTRO_INGRESO,
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function customer()
    {
        return $this->belongsTo(Tercero::class, 'tercero_id');
    }

    public function tercero()
    {
        return $this->belongsTo(Tercero::class, 'tercero_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reversalUser()
    {
        return $this->belongsTo(User::class, 'reversal_user_id');
    }

    public function tipoComprobante()
    {
        return $this->belongsTo(TipoComprobante::class, 'tipo_comprobante_id');
    }

    public function formaPago()
    {
        return $this->belongsTo(FormaPago::class, 'forma_pago_id');
    }

    public function centroCosto()
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function destinos()
    {
        return $this->hasMany(ComprobanteIngresoDestino::class, 'comprobante_ingreso_id');
    }

    public function aplicaciones()
    {
        return $this->hasMany(ComprobanteIngresoAplicacion::class, 'comprobante_ingreso_id');
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function isReversed(): bool
    {
        return (bool) $this->reversed_at;
    }

    /** True si este ingreso está ligado a una o más cuentas por cobrar (cobro). */
    public function isCobroCuenta(): bool
    {
        return $this->type === self::TYPE_COBRO_CUENTA && $this->aplicaciones()->exists();
    }

    public function esReciboCaja(): bool
    {
        return $this->tipo_comprobante_id
            && $this->tipoComprobante
            && $this->tipoComprobante->familia === TipoComprobante::FAMILIA_RC;
    }
}
