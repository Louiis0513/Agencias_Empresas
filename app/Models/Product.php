<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cascarón mínimo del maestro de ítems (producto/servicio).
 * Se rediseñará con categoría contable estilo Siigo.
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'categoria_contable_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function categoriaContable()
    {
        return $this->belongsTo(CategoriaContable::class, 'categoria_contable_id');
    }

    public function invoiceDetails()
    {
        return $this->hasMany(InvoiceDetail::class);
    }
}
