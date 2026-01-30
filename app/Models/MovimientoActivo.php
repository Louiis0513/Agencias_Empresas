<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class MovimientoActivo extends Model
{
    use HasFactory;

    protected $table = 'movimientos_activo';

    protected $fillable = [
        'store_id',
        'user_id',
        'activo_id',
        'purchase_id',
        'type',
        'quantity',
        'description',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public const TYPE_ENTRADA = 'ENTRADA';
    public const TYPE_SALIDA = 'SALIDA';

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activo()
    {
        return $this->belongsTo(Activo::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopePorActivo(Builder $query, int $activoId): void
    {
        $query->where('activo_id', $activoId);
    }

    public function scopePorTipo(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }
}
