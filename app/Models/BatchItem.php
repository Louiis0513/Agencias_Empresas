<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'quantity',
        'features',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'features' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
