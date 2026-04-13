<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportDocumentInventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_document_id',
        'product_id',
        'description',
        'quantity',
        'unit_cost',
        'tax_rate',
        'tax_amount',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function supportDocument()
    {
        return $this->belongsTo(SupportDocument::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
