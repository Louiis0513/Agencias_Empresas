<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'user_id',
        'proveedor_id',
        'status',
        'payment_status',
        'payment_type',
        'invoice_number',
        'invoice_date',
        'image_path',
        'total',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'total' => 'decimal:2',
    ];

    public const STATUS_BORRADOR = 'BORRADOR';
    public const STATUS_APROBADO = 'APROBADO';
    public const STATUS_ANULADO = 'ANULADO';

    public const PAYMENT_PENDIENTE = 'PENDIENTE';
    public const PAYMENT_PAGADO = 'PAGADO';
    public const PAYMENT_TYPE_CONTADO = 'CONTADO';
    public const PAYMENT_TYPE_CREDITO = 'CREDITO';

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

    public function details()
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    public function accountPayable()
    {
        return $this->hasOne(AccountPayable::class);
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopePorStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopePorPaymentStatus(Builder $query, string $paymentStatus): void
    {
        $query->where('payment_status', $paymentStatus);
    }

    public function isBorrador(): bool
    {
        return $this->status === self::STATUS_BORRADOR;
    }

    public function isAprobado(): bool
    {
        return $this->status === self::STATUS_APROBADO;
    }

    public function isAnulado(): bool
    {
        return $this->status === self::STATUS_ANULADO;
    }

    public function isPendientePago(): bool
    {
        return $this->payment_status === self::PAYMENT_PENDIENTE;
    }
}
