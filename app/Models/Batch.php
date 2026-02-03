<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'product_id',
        'reference',
        'expiration_date',
    ];

    protected $casts = [
        'expiration_date' => 'date',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batchItems()
    {
        return $this->hasMany(BatchItem::class);
    }

    /** Cantidad total en el lote (suma de batch_items). */
    public function getTotalQuantityAttribute(): int
    {
        return (int) $this->batchItems()->sum('quantity');
    }
}
