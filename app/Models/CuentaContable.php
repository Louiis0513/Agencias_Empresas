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

    public const CATEGORIA_CUENTAS_POR_COBRAR = 'Cuentas por cobrar';

    public const CATEGORIA_OTROS_ACTIVOS_CORRIENTES = 'Otros activos corrientes';

    public const CATEGORIA_INVENTARIOS = 'Inventarios';

    public const CATEGORIA_ACTIVOS_FIJOS = 'Activos fijos';

    public const CATEGORIA_OTROS_ACTIVOS = 'Otros activos';

    public const CATEGORIA_CUENTAS_POR_PAGAR = 'Cuentas por pagar';

    public const CATEGORIA_OTROS_PASIVOS_CORRIENTES = 'Otros pasivos corrientes';

    public const CATEGORIA_PASIVO_CORTO_PLAZO = 'Pasivo corto plazo';

    public const CATEGORIA_PASIVOS_LARGOS_PLAZOS = 'Pasivos largos plazos';

    public const CATEGORIA_OTROS_PASIVOS = 'Otros pasivos';

    public const CATEGORIA_PATRIMONIO = 'Patrimonio';

    public const CATEGORIA_INGRESOS = 'Ingresos';

    public const CATEGORIA_OTROS_INGRESOS = 'Otros ingresos';

    public const CATEGORIA_COSTO_VENTAS = 'Costo de ventas';

    public const CATEGORIA_GASTOS = 'Gastos';

    public const CATEGORIA_OTROS_GASTOS = 'Otros gastos';

    public const CATEGORIA_ORDEN = 'Orden';

    public const CATEGORIA_GASTO_NOMINA = 'Gasto - Nómina';

    public const RELACION_FORMAS_DE_PAGO = 'Formas de pago';

    public const RELACION_INVENTARIO = 'Grupo de inventarios - Inventario';

    public const RELACION_INGRESOS_OPERACIONALES = 'Ingresos operacionales';

    public const RELACION_COSTO_VENTAS = 'Costo de ventas';

    public const RELACION_DEVOLUCIONES_VENTAS = 'Devoluciones en ventas';

    public const MANEJA_VENCIMIENTOS_NO = 'No maneja vencimiento';

    /**
     * Categorías de reporte estilo Siigo (lista fija del sistema).
     * Se usan al crear/editar auxiliares y en generación de reportes.
     */
    public const CATEGORIAS_SUGERIDAS = [
        self::CATEGORIA_CAJA_BANCOS,
        self::CATEGORIA_CUENTAS_POR_COBRAR,
        self::CATEGORIA_OTROS_ACTIVOS_CORRIENTES,
        self::CATEGORIA_INVENTARIOS,
        self::CATEGORIA_ACTIVOS_FIJOS,
        self::CATEGORIA_OTROS_ACTIVOS,
        self::CATEGORIA_CUENTAS_POR_PAGAR,
        self::CATEGORIA_OTROS_PASIVOS_CORRIENTES,
        self::CATEGORIA_PASIVO_CORTO_PLAZO,
        self::CATEGORIA_PASIVOS_LARGOS_PLAZOS,
        self::CATEGORIA_OTROS_PASIVOS,
        self::CATEGORIA_PATRIMONIO,
        self::CATEGORIA_INGRESOS,
        self::CATEGORIA_OTROS_INGRESOS,
        self::CATEGORIA_COSTO_VENTAS,
        self::CATEGORIA_GASTOS,
        self::CATEGORIA_OTROS_GASTOS,
        self::CATEGORIA_ORDEN,
        self::CATEGORIA_GASTO_NOMINA,
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

    /** Longitud máxima de hoja (subauxiliar). */
    public const MAX_CODIGO_HOJA = 10;

    /** Longitudes de padre desde las que se puede crear hijo. */
    public const LONGITUDES_PADRE_HIJO = [1, 2, 4, 6, 8];

    public const NIVEL_LABELS = [
        1 => 'Clase',
        2 => 'Grupo',
        4 => 'Cuenta',
        6 => 'Subcuenta',
        8 => 'Auxiliar',
        10 => 'Subauxiliar',
    ];

    public const ACCION_CREAR_HIJO = [
        1 => 'Grupo',
        2 => 'Cuenta',
        4 => 'Subcuenta',
        6 => 'Auxiliar',
        8 => 'Subauxiliar',
    ];

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

    /**
     * Cuenta usable en impuestos / asientos: auxiliar transaccional
     * o hoja de 6 dígitos transaccional (estilo Siigo, p. ej. Impoconsumo por valor).
     */
    public function esUsableEnDocumentoContable(): bool
    {
        if (! $this->activo || ! $this->esTransaccional()) {
            return false;
        }

        if ($this->es_auxiliar) {
            return true;
        }

        return self::longitudCodigo($this->codigo) === self::MAX_CODIGO_BASE;
    }

    public function scopeUsablesEnDocumentoContable($query)
    {
        return $query->activas()
            ->transaccionales()
            ->where(function ($q) {
                $q->where('es_auxiliar', true)
                    ->orWhereRaw('LENGTH(codigo) = ?', [self::MAX_CODIGO_BASE]);
            });
    }

    public static function claseDesdeCodigo(string $codigo): ?string
    {
        $digito = substr(preg_replace('/\D/', '', $codigo) ?? '', 0, 1);

        return self::CLASES_POR_DIGITO[$digito] ?? null;
    }

    /** Naturaleza deudora PUC: clases 1, 5, 6, 7, 8. */
    public static function esNaturalezaDeudora(string $codigo): bool
    {
        $digito = substr(preg_replace('/\D/', '', $codigo) ?? '', 0, 1);

        return in_array($digito, ['1', '5', '6', '7', '8'], true);
    }

    public static function firmarSaldo(string $codigo, float|string $debito, float|string $credito): float
    {
        $debito = (float) $debito;
        $credito = (float) $credito;

        return self::esNaturalezaDeudora($codigo)
            ? round($debito - $credito, 2)
            : round($credito - $debito, 2);
    }

    public static function esCodigoAuxiliar(string $codigo): bool
    {
        $soloDigitos = preg_replace('/\D/', '', $codigo) ?? '';

        return strlen($soloDigitos) > self::MAX_CODIGO_BASE;
    }

    public static function longitudCodigo(string $codigo): int
    {
        return strlen(preg_replace('/\D/', '', $codigo) ?? '');
    }

    public static function labelNivelPorLongitud(int $longitud): string
    {
        return self::NIVEL_LABELS[$longitud] ?? 'Cuenta';
    }

    public static function labelAccionCrearHijo(int $longitudPadre): ?string
    {
        return self::ACCION_CREAR_HIJO[$longitudPadre] ?? null;
    }

    /** Dígitos del sufijo al crear hijo: Clase→Grupo = 1; resto = 2. */
    public static function digitosSufijoHijo(int $longitudPadre): int
    {
        return $longitudPadre === 1 ? 1 : 2;
    }

    public static function longitudHijoEsperada(int $longitudPadre): int
    {
        return $longitudPadre + self::digitosSufijoHijo($longitudPadre);
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
