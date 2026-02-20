<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class SesionCaja extends Model
{
    use HasFactory;

    protected $table = 'sesiones_caja';

    protected $fillable = [
        'store_id',
        'user_id',
        'opened_at',
        'nota_apertura',
        'closed_at',
        'closed_by_user_id',
        'nota_cierre',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoBolsillo::class, 'sesion_caja_id');
    }

    public function detalles()
    {
        return $this->hasMany(SesionCajaDetalle::class, 'sesion_caja_id');
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopeAbierta(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    public function isAbierta(): bool
    {
        return $this->closed_at === null;
    }
}
