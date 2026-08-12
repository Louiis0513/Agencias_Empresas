<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoComprobante extends Model
{
    use HasFactory;

    public const FAMILIA_FV = 'FV';

    public const FAMILIA_RC = 'RC';

    public const FAMILIA_FC = 'FC';

    public const FAMILIA_RP = 'RP';

    public const FAMILIA_CC = 'CC';

    public const FAMILIA_A = 'A';

    public const FAMILIA_NT = 'NT';

    public const FAMILIA_CF = 'CF';

    public const FAMILIAS = [
        self::FAMILIA_FV,
        self::FAMILIA_RC,
        self::FAMILIA_FC,
        self::FAMILIA_RP,
        self::FAMILIA_CC,
        self::FAMILIA_A,
        self::FAMILIA_NT,
        self::FAMILIA_CF,
    ];

    public const LIBRO_VENTAS = 'ventas';

    public const LIBRO_VENTAS_DEVOLUCIONES = 'ventas_devoluciones';

    public const LIBRO_COMPRAS = 'compras';

    public const LIBRO_COMPRAS_DEVOLUCIONES = 'compras_devoluciones';

    public const LIBROS_OFICIALES = [
        self::LIBRO_VENTAS,
        self::LIBRO_VENTAS_DEVOLUCIONES,
        self::LIBRO_COMPRAS,
        self::LIBRO_COMPRAS_DEVOLUCIONES,
    ];

    protected $table = 'tipos_comprobante';

    protected $fillable = [
        'store_id',
        'familia',
        'codigo',
        'nombre',
        'titulo',
        'prefijo',
        'numeracion_automatica',
        'siguiente_numero',
        'activo',
        'maneja_centro_costos',
        'centro_costo_obligatorio',
        'centro_costo_default_id',
        'libro_oficial',
        'cuenta_anticipos_id',
    ];

    protected function casts(): array
    {
        return [
            'numeracion_automatica' => 'boolean',
            'siguiente_numero' => 'integer',
            'activo' => 'boolean',
            'maneja_centro_costos' => 'boolean',
            'centro_costo_obligatorio' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function centroCostoDefault(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_default_id');
    }

    public function cuentaAnticipos(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_anticipos_id');
    }

    public function comprobantesContables(): HasMany
    {
        return $this->hasMany(ComprobanteContable::class);
    }

    /** ¿Mostrar campo de centro de costo en el documento? */
    public function manejaCentroCostos(): bool
    {
        return (bool) $this->maneja_centro_costos;
    }

    /** ¿Exigir subcentro cuando el tipo maneja centros? */
    public function exigeCentroCostos(): bool
    {
        return $this->manejaCentroCostos() && (bool) $this->centro_costo_obligatorio;
    }

    public static function etiquetasFamiliasGrupo(): array
    {
        return [
            self::FAMILIA_FV => 'FACTURAS',
            self::FAMILIA_FC => 'FACTURAS DE COMPRA',
            self::FAMILIA_RC => 'RECIBOS DE CAJA',
            self::FAMILIA_RP => 'RECIBOS DE PAGO',
            self::FAMILIA_CC => 'COMPROBANTES CONTABLES',
            self::FAMILIA_A => 'AJUSTES DE INVENTARIO',
            self::FAMILIA_NT => 'NOTAS DE TRASLADO',
            self::FAMILIA_CF => 'CONTEOS FÍSICOS',
        ];
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

    public function etiquetaFamilia(): string
    {
        return match ($this->familia) {
            self::FAMILIA_FV => 'Factura de venta',
            self::FAMILIA_RC => 'Recibo de caja',
            self::FAMILIA_FC => 'Factura de compra',
            self::FAMILIA_RP => 'Recibo de pago / egreso',
            self::FAMILIA_CC => 'Comprobante contable',
            self::FAMILIA_A => 'Ajuste / Saldo inicial de inventario',
            self::FAMILIA_NT => 'Nota de traslado entre bodegas',
            self::FAMILIA_CF => 'Conteo físico',
            default => $this->familia,
        };
    }

    public function etiquetaLibroOficial(): string
    {
        return self::etiquetasLibrosOficiales()[$this->libro_oficial] ?? '—';
    }

    /**
     * @return array<string, string>
     */
    public static function etiquetasLibrosOficiales(): array
    {
        return [
            self::LIBRO_VENTAS => 'Libro ventas - Ventas',
            self::LIBRO_VENTAS_DEVOLUCIONES => 'Libro ventas - Devoluciones',
            self::LIBRO_COMPRAS => 'Libro compras - Compras',
            self::LIBRO_COMPRAS_DEVOLUCIONES => 'Libro compras - Devoluciones',
        ];
    }

    public static function etiquetasFamilias(): array
    {
        return [
            self::FAMILIA_FV => 'Factura de venta',
            self::FAMILIA_RC => 'Recibo de caja',
            self::FAMILIA_FC => 'Factura de compra',
            self::FAMILIA_RP => 'Recibo de pago / egreso',
            self::FAMILIA_CC => 'Comprobante contable',
            self::FAMILIA_A => 'Ajuste / Saldo inicial de inventario',
            self::FAMILIA_NT => 'Nota de traslado entre bodegas',
            self::FAMILIA_CF => 'Conteo físico',
        ];
    }
}
