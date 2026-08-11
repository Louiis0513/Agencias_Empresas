<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoContable extends Model
{
    use HasFactory;

    protected $table = 'movimientos_contables';

    protected $fillable = [
        'comprobante_contable_id',
        'documento_inventario_id',
        'store_id',
        'cuenta_contable_id',
        'tercero_id',
        'centro_costo_id',
        'detalle_contable',
        'descripcion',
        'debito',
        'credito',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'debito' => 'decimal:2',
            'credito' => 'decimal:2',
            'orden' => 'integer',
        ];
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(ComprobanteContable::class, 'comprobante_contable_id');
    }

    public function documentoInventario(): BelongsTo
    {
        return $this->belongsTo(DocumentoInventario::class, 'documento_inventario_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class);
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function fechaAsiento(): ?\Carbon\CarbonInterface
    {
        return $this->comprobante?->fecha ?? $this->documentoInventario?->fecha;
    }

    public function numeroAsiento(): ?string
    {
        return $this->comprobante?->numero ?? $this->documentoInventario?->numero;
    }

    public function glosaAsiento(): ?string
    {
        if ($this->descripcion) {
            return $this->descripcion;
        }

        return $this->comprobante?->descripcion
            ?? $this->documentoInventario?->observaciones;
    }
}
