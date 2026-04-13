<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'user_id',
        'proveedor_id',
        'comprobante_egreso_id',
        'status',
        'payment_status',
        'due_date',
        'doc_prefix',
        'doc_number',
        'issue_date',
        'subtotal',
        'tax_total',
        'total',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'issue_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public const STATUS_BORRADOR = 'BORRADOR';

    public const STATUS_APROBADO = 'APROBADO';

    public const STATUS_ANULADO = 'ANULADO';

    public const PAYMENT_PAGADO = 'PAGADO';

    public const PAYMENT_PENDIENTE = 'PENDIENTE';

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function comprobanteEgreso()
    {
        return $this->belongsTo(ComprobanteEgreso::class);
    }

    public function inventoryItems()
    {
        return $this->hasMany(SupportDocumentInventoryItem::class);
    }

    public function serviceItems()
    {
        return $this->hasMany(SupportDocumentServiceItem::class);
    }

    public function movimientosInventario()
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }
}
