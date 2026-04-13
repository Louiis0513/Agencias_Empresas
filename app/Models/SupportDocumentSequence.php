<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportDocumentSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'prefix',
        'last_number',
    ];

    protected $casts = [
        'last_number' => 'integer',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
