<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ActivoLocation extends Model
{
    use HasFactory;

    protected $table = 'activo_locations';

    protected $fillable = [
        'store_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function activos()
    {
        return $this->hasMany(Activo::class, 'location_id');
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
