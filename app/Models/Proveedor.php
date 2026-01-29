<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = [
        'store_id',
        'nombre',
        'numero_celular',
        'telefono',
        'email',
        'nit',
        'direccion',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function productos()
    {
        return $this->belongsToMany(Product::class, 'producto_proveedor')
            ->withTimestamps();
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('estado', true);
    }

    public function scopeBuscar(Builder $query, ?string $termino): void
    {
        if ($termino) {
            $query->where(function ($q) use ($termino) {
                $q->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('email', 'like', "%{$termino}%")
                    ->orWhere('nit', 'like', "%{$termino}%")
                    ->orWhere('numero_celular', 'like', "%{$termino}%")
                    ->orWhere('telefono', 'like', "%{$termino}%")
                    ->orWhereHas('productos', function ($subQ) use ($termino) {
                        $subQ->where('name', 'like', "%{$termino}%")
                            ->orWhere('sku', 'like', "%{$termino}%")
                            ->orWhere('barcode', 'like', "%{$termino}%");
                    });
            });
        }
    }
}
