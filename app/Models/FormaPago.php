<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormaPago extends Model
{
    use HasFactory;

    public const APLICA_CARTERA = 'cartera';

    public const APLICA_PROVEEDORES = 'proveedores';

    public const APLICA_AMBOS = 'ambos';

    /** Alcance documental estilo Siigo. */
    public const APLICA_A = [
        self::APLICA_CARTERA,
        self::APLICA_PROVEEDORES,
        self::APLICA_AMBOS,
    ];

    public const APLICA_A_LABELS = [
        self::APLICA_CARTERA => 'Cartera - Factura de venta y Recibo de caja',
        self::APLICA_PROVEEDORES => 'Proveedores - Factura de compra y Recibo de pago',
        self::APLICA_AMBOS => 'Cartera/Proveedores',
    ];

    /**
     * Catálogo fijo DIAN PaymentMeansCode (anexo técnico factura electrónica).
     * Código => descripción.
     */
    public const MEDIOS_PAGO_DIAN = [
        '1' => 'Instrumento no definido',
        '2' => 'Crédito ACH',
        '3' => 'Débito ACH',
        '4' => 'Reversión débito de demanda ACH',
        '5' => 'Reversión crédito de demanda ACH',
        '6' => 'Crédito de demanda ACH',
        '7' => 'Débito de demanda ACH',
        '9' => 'Clearing Nacional o Regional',
        '10' => 'Efectivo',
        '11' => 'Reversión Crédito Ahorro',
        '12' => 'Reversión Débito Ahorro',
        '13' => 'Crédito Ahorro',
        '14' => 'Débito Ahorro',
        '15' => 'Bookentry Crédito',
        '16' => 'Bookentry Débito',
        '17' => 'Concentración dinero (cash concentration/disbursement) Crédito (CCD)',
        '18' => 'Concentración dinero (cash concentration/disbursement) Débito (CCD)',
        '19' => 'Crédito Pago negocio corporativo (CTP)',
        '20' => 'Cheque',
        '21' => 'Proyecto bancario',
        '22' => 'Proyecto bancario certificado',
        '23' => 'Cheque bancario de gerencia',
        '24' => 'Nota cambiaria esperando aceptación',
        '25' => 'Cheque certificado',
        '26' => 'Cheque Local',
        '27' => 'Débito Pago Negocio Corporativo (CTP)',
        '28' => 'Crédito Negocio Intercambio Corporativo (CTX)',
        '29' => 'Débito Negocio Intercambio Corporativo (CTX)',
        '30' => 'Transferencia Crédito',
        '31' => 'Transferencia Débito',
        '32' => 'Desembolso Crédito plus (CCD+)',
        '33' => 'Desembolso Débito plus (CCD+)',
        '34' => 'Pago y depósito pre acordado (PPD)',
        '35' => 'Desembolso Crédito (CCD)',
        '36' => 'Desembolso Débito (CCD)',
        '37' => 'Pago Negocio Corporativo Ahorros Crédito (CTP)',
        '38' => 'Pago Negocio Corporativo Ahorros Débito (CTP)',
        '39' => 'Crédito Intercambio Corporativo (CTX)',
        '40' => 'Débito Intercambio Corporativo (CTX)',
        '41' => 'Desembolso Crédito plus (CCD+)',
        '42' => 'Consignación bancaria',
        '43' => 'Desembolso Débito plus (CCD+)',
        '44' => 'Nota cambiaria',
        '45' => 'Transferencia Crédito Bancario',
        '46' => 'Transferencia Débito Interbancario',
        '47' => 'Transferencia Débito Bancaria',
        '48' => 'Tarjeta Crédito',
        '49' => 'Tarjeta Débito',
        '50' => 'Postgiro',
        '51' => 'Telex estándar bancario',
        '52' => 'Pago comercial urgente',
        '53' => 'Pago Tesorería Urgente',
        '60' => 'Nota promisoria',
        '61' => 'Nota promisoria firmada por el acreedor',
        '62' => 'Nota promisoria firmada por el acreedor, avalada por el banco',
        '63' => 'Nota promisoria firmada por el acreedor, avalada por un tercero',
        '64' => 'Nota promisoria firmada por el banco',
        '65' => 'Nota promisoria firmada por un banco avalada por otro banco',
        '66' => 'Nota promisoria firmada',
        '67' => 'Nota promisoria firmada por un tercero avalada por un banco',
        '70' => 'Retiro de nota por el acreedor',
        '71' => 'Bonos',
        '72' => 'Vales',
        '74' => 'Retiro de nota por el acreedor sobre un banco',
        '75' => 'Retiro de nota por el acreedor, avalada por otro banco',
        '76' => 'Retiro de nota por el acreedor, sobre un banco avalada por un tercero',
        '77' => 'Retiro de una nota por el acreedor sobre un tercero',
        '78' => 'Retiro de una nota por el acreedor sobre un tercero avalada por un banco',
        '91' => 'Nota bancaria transferible',
        '92' => 'Cheque local transferible',
        '93' => 'Giro referenciado',
        '94' => 'Giro urgente',
        '95' => 'Giro formato abierto',
        '96' => 'Método de pago solicitado no usado',
        '97' => 'Clearing entre partners',
        'ZZZ' => 'Otro',
    ];

    protected $table = 'formas_pago';

    protected $fillable = [
        'store_id',
        'en_uso',
        'codigo',
        'nombre',
        'aplica_a',
        'cuenta_contable_id',
        'medio_pago_dian',
        'es_pago_en_linea',
    ];

    protected function casts(): array
    {
        return [
            'en_uso' => 'boolean',
            'es_pago_en_linea' => 'boolean',
            'codigo' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_contable_id');
    }

    public function scopeDeStore($query, Store|int $store)
    {
        $storeId = $store instanceof Store ? $store->id : $store;

        return $query->where('store_id', $storeId);
    }

    public function scopeEnUso($query)
    {
        return $query->where('en_uso', true);
    }

    public function labelAplicaA(): string
    {
        return self::APLICA_A_LABELS[$this->aplica_a] ?? $this->aplica_a;
    }

    public function labelMedioPagoDian(): ?string
    {
        if ($this->medio_pago_dian === null || $this->medio_pago_dian === '') {
            return null;
        }

        $desc = self::MEDIOS_PAGO_DIAN[$this->medio_pago_dian] ?? null;

        return $desc
            ? $this->medio_pago_dian.' — '.$desc
            : $this->medio_pago_dian;
    }
}
