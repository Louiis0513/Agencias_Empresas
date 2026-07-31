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

    public const CATEGORIA_INVENTARIOS = 'Inventarios';

    public const CATEGORIA_INGRESOS = 'Ingresos';

    public const CATEGORIA_COSTO_VENTAS = 'Costo de ventas';

    public const RELACION_FORMAS_DE_PAGO = 'Formas de pago';

    public const RELACION_INVENTARIO = 'Grupo de inventarios - Inventario';

    public const RELACION_INGRESOS_OPERACIONALES = 'Ingresos operacionales';

    public const RELACION_COSTO_VENTAS = 'Costo de ventas';

    public const RELACION_DEVOLUCIONES_VENTAS = 'Devoluciones en ventas';

    public const MANEJA_VENCIMIENTOS_NO = 'No maneja vencimiento';

    /** Categorías sugeridas al crear auxiliares (estilo Siigo). */
    public const CATEGORIAS_SUGERIDAS = [
        self::CATEGORIA_CAJA_BANCOS,
        self::CATEGORIA_INVENTARIOS,
        self::CATEGORIA_INGRESOS,
        self::CATEGORIA_COSTO_VENTAS,
    ];

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

    /** Padres de 6 dígitos típicos para mercancía (revenda). */
    public const PADRE_INVENTARIO_MERCANCIA = '143501';

    public const PADRE_INGRESO_COMERCIO = '413501';

    public const PADRE_COSTO_COMERCIO = '613505';

    public const PADRE_DEVOLUCION_VENTAS = '417505';

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

    public function movimientosContables(): HasMany
    {
        return $this->hasMany(MovimientoContable::class);
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

    /**
     * Perfil contable según el código padre (para defaults de auxiliar).
     * disponible | inventario | costo | devolucion | ingreso | null
     */
    public static function perfilDesdeCodigo(string $codigo): ?string
    {
        $digitos = preg_replace('/\D/', '', $codigo) ?? '';

        if ($digitos === '') {
            return null;
        }

        if (str_starts_with($digitos, '11')) {
            return 'disponible';
        }

        if (str_starts_with($digitos, '14')) {
            return 'inventario';
        }

        if (str_starts_with($digitos, '61') || str_starts_with($digitos, '62')) {
            return 'costo';
        }

        if (str_starts_with($digitos, '4175')) {
            return 'devolucion';
        }

        if (str_starts_with($digitos, '4')) {
            return 'ingreso';
        }

        return null;
    }

    /**
     * Defaults de categoría / relación / vencimientos / clase al crear auxiliar.
     *
     * @return array{categoria: ?string, relacion_con: ?string, maneja_vencimientos: string, clase: ?string}
     */
    public static function defaultsParaCodigoPadre(string $codigoPadre): array
    {
        $clase = self::claseDesdeCodigo($codigoPadre);
        $perfil = self::perfilDesdeCodigo($codigoPadre);

        return match ($perfil) {
            'disponible' => [
                'categoria' => self::CATEGORIA_CAJA_BANCOS,
                'relacion_con' => self::RELACION_FORMAS_DE_PAGO,
                'maneja_vencimientos' => self::MANEJA_VENCIMIENTOS_NO,
                'clase' => $clase ?? 'Activo',
            ],
            'inventario' => [
                'categoria' => self::CATEGORIA_INVENTARIOS,
                'relacion_con' => self::RELACION_INVENTARIO,
                'maneja_vencimientos' => self::MANEJA_VENCIMIENTOS_NO,
                'clase' => $clase ?? 'Activo',
            ],
            'costo' => [
                'categoria' => self::CATEGORIA_COSTO_VENTAS,
                'relacion_con' => self::RELACION_COSTO_VENTAS,
                'maneja_vencimientos' => self::MANEJA_VENCIMIENTOS_NO,
                'clase' => $clase ?? 'Costos de venta',
            ],
            'devolucion' => [
                'categoria' => self::CATEGORIA_INGRESOS,
                'relacion_con' => self::RELACION_DEVOLUCIONES_VENTAS,
                'maneja_vencimientos' => self::MANEJA_VENCIMIENTOS_NO,
                'clase' => $clase ?? 'Ingresos',
            ],
            'ingreso' => [
                'categoria' => self::CATEGORIA_INGRESOS,
                'relacion_con' => self::RELACION_INGRESOS_OPERACIONALES,
                'maneja_vencimientos' => self::MANEJA_VENCIMIENTOS_NO,
                'clase' => $clase ?? 'Ingresos',
            ],
            default => [
                'categoria' => null,
                'relacion_con' => null,
                'maneja_vencimientos' => self::MANEJA_VENCIMIENTOS_NO,
                'clase' => $clase,
            ],
        };
    }
}
