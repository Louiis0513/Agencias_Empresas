<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'user_id',
        'customer_id',
        'subtotal',
        'tax',
        'discount',
        'total',
        'status',        // PAID, PENDING, VOID
        'payment_method' // CASH, CARD, TRANSFER
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // --- RELACIONES ---
    public function details() 
    { 
        return $this->hasMany(InvoiceDetail::class); 
    }
    
    public function user() 
    { 
        return $this->belongsTo(User::class); 
    }
    
    public function store() 
    { 
        return $this->belongsTo(Store::class); 
    }
    
    public function customer() 
    { 
        return $this->belongsTo(Customer::class); 
    }

    // --- SCOPES ---
    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopeBuscar(Builder $query, ?string $termino): void
    {
        if ($termino) {
            $query->where(function($q) use ($termino) {
                $q->where('id', $termino)
                  ->orWhere('total', 'like', "%{$termino}%");
            });
        }
    }
}
