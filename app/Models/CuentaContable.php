<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CuentaContable extends Model
{
    use HasFactory;

    public const NIVEL_TRANSACCIONAL = 'Transaccional';

    public const ORIGEN_PLANTILLA = 'plantilla';

    public const ORIGEN_MANUAL = 'manual';

    public const CATEGORIA_CAJA_BANCOS = 'Caja - Bancos';

    public const RELACION_FORMAS_DE_PAGO = 'Formas de pago';

    public const MANEJA_VENCIMIENTOS_NO = 'No maneja vencimiento';

    /** Padres de 6 dígitos usados al crear bolsillos desde Caja. */
    public const PADRE_EFECTIVO = '110505';

    public const PADRE_BANCO_CORRIENTE_COP = '111005';

    public const PADRE_BANCO_AHORRO = '112005';

    public const PADRE_BANCO_DIVISAS = '111010';

    public const TIPOS_BOLSILLO_PADRE = [
        'efectivo' => self::PADRE_EFECTIVO,
        'corriente_cop' => self::PADRE_BANCO_CORRIENTE_COP,
        'ahorro' => self::PADRE_BANCO_AHORRO,
        'divisas' => self::PADRE_BANCO_DIVISAS,
    ];

    public const CLASES_POR_DIGITO = [
        '1' => 'Activo',
        '2' => 'Pasivo',
        '3' => 'Patrimonio',
        '4' => 'Ingresos',
        '5' => 'Gastos',
        '6' => 'Costos de venta',
        '7' => 'Costos de producción o de operación',
        '8' => 'Cuentas de orden deudoras',
        '9' => 'Cuentas de orden acreedoras',
    ];

    /** Longitud máxima del código PUC base (sin auxiliares de empresa). */
    public const MAX_CODIGO_BASE = 6;

    protected $table = 'cuentas_contables';

    protected $fillable = [
        'store_id',
        'codigo',
        'nombre',
        'clase',
        'categoria',
        'relacion_con',
        'maneja_vencimientos',
        'diferencia_fiscal',
        'activo',
        'nivel_agrupacion',
        'es_auxiliar',
        'origen',
        'cuenta_padre_id',
    ];

    protected function casts(): array
    {
        return [
            'diferencia_fiscal' => 'boolean',
            'activo' => 'boolean',
            'es_auxiliar' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cuenta_padre_id');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'cuenta_padre_id');
    }

    public function bolsillo(): HasOne
    {
        return $this->hasOne(Bolsillo::class, 'cuenta_contable_id');
    }

    public function scopeDeStore($query, Store|int $store)
    {
        $storeId = $store instanceof Store ? $store->id : $store;

        return $query->where('store_id', $storeId);
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeTransaccionales($query)
    {
        return $query->where('nivel_agrupacion', self::NIVEL_TRANSACCIONAL);
    }

    public function scopeBase($query)
    {
        return $query->where('es_auxiliar', false);
    }

    public function esTransaccional(): bool
    {
        return $this->nivel_agrupacion === self::NIVEL_TRANSACCIONAL;
    }

    public static function claseDesdeCodigo(string $codigo): ?string
    {
        $digito = substr(preg_replace('/\D/', '', $codigo) ?? '', 0, 1);

        return self::CLASES_POR_DIGITO[$digito] ?? null;
    }

    public static function esCodigoAuxiliar(string $codigo): bool
    {
        $soloDigitos = preg_replace('/\D/', '', $codigo) ?? '';

        return strlen($soloDigitos) > self::MAX_CODIGO_BASE;
    }

    /**
     * Disponible operable como forma de pago (caja / bancos / ahorro).
     * Excluye remesas en tránsito (1115).
     */
    public function esDisponibleOperable(): bool
    {
        return self::codigoEsDisponibleOperable($this->codigo);
    }

    public static function codigoEsDisponibleOperable(string $codigo): bool
    {
        $digitos = preg_replace('/\D/', '', $codigo) ?? '';

        return str_starts_with($digitos, '1105')
            || str_starts_with($digitos, '1110')
            || str_starts_with($digitos, '1120');
    }

    public function esBancoSegunCodigo(): bool
    {
        return self::codigoEsBanco($this->codigo);
    }

    public static function codigoEsBanco(string $codigo): bool
    {
        $digitos = preg_replace('/\D/', '', $codigo) ?? '';

        return str_starts_with($digitos, '1110')
            || str_starts_with($digitos, '1120');
    }

    public static function esCodigoDisponible(string $codigo): bool
    {
        $digitos = preg_replace('/\D/', '', $codigo) ?? '';

        return str_starts_with($digitos, '11');
    }
}
