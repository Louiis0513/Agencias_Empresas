<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportDocumentServiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_document_id',
        'service_name',
        'description',
        'quantity',
        'unit_cost',
        'tax_rate',
        'tax_amount',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function supportDocument()
    {
        return $this->belongsTo(SupportDocument::class);
    }
}
