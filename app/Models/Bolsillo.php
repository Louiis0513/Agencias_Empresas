<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Bolsillo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bolsillos';

    protected $fillable = [
        'store_id',
        'name',
        'detalles',
        'saldo',
        'is_bank_account',
        'is_active',
    ];

    protected $casts = [
        'saldo' => 'decimal:2',
        'is_bank_account' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoBolsillo::class, 'bolsillo_id');
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeBuscar(Builder $query, ?string $termino): void
    {
        if ($termino) {
            $query->where(function ($q) use ($termino) {
                $q->where('name', 'like', "%{$termino}%")
                  ->orWhere('detalles', 'like', "%{$termino}%");
            });
        }
    }
}
