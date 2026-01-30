<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Activo extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'name',
        'code',
        'description',
        'quantity',
        'unit_cost',
        'location',
        'is_active',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /** Valor total del activo (cantidad × costo unitario). */
    public function getValorTotalAttribute(): float
    {
        return (float) ($this->quantity * $this->unit_cost);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoActivo::class)->orderByDesc('created_at');
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeBuscar(Builder $query, string $term): void
    {
        $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }
}
