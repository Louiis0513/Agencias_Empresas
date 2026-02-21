<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Cotizacion extends Model
{
    use HasFactory;

    protected $table = 'cotizaciones';

    protected $fillable = [
        'store_id',
        'user_id',
        'customer_id',
        'nota',
        'vence_at',
        'estado',
        'invoice_id',
    ];

    protected $casts = [
        'vence_at' => 'date',
    ];

    public const ESTADO_BORRADOR = 'borrador';
    public const ESTADO_FACTURADA = 'facturada';

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(CotizacionItem::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }
}
