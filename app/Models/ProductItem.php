<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductItem extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'AVAILABLE';
    public const STATUS_SOLD = 'SOLD';
    public const STATUS_RESERVED = 'RESERVED';
    public const STATUS_DEFECTIVE = 'DEFECTIVE';

    protected $fillable = [
        'store_id',
        'product_id',
        'serial_number',
        'batch',
        'expiration_date',
        'cost',
        'status',
        'features',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'expiration_date' => 'date',
        'features' => 'array',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isSold(): bool
    {
        return $this->status === self::STATUS_SOLD;
    }

    public function isReserved(): bool
    {
        return $this->status === self::STATUS_RESERVED;
    }

    public function isDefective(): bool
    {
        return $this->status === self::STATUS_DEFECTIVE;
    }

    /** Opciones de estado para selects. */
    public static function estadosDisponibles(): array
    {
        return [
            self::STATUS_AVAILABLE => 'Disponible',
            self::STATUS_SOLD => 'Vendido',
            self::STATUS_RESERVED => 'Reservado',
            self::STATUS_DEFECTIVE => 'Defectuoso',
        ];
    }
}
