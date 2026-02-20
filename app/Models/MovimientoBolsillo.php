<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class MovimientoBolsillo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'movimientos_bolsillo';

    protected $fillable = [
        'store_id',
        'bolsillo_id',
        'sesion_caja_id',
        'comprobante_egreso_id',
        'comprobante_ingreso_id',
        'type',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public const TYPE_INCOME = 'INCOME';
    public const TYPE_EXPENSE = 'EXPENSE';

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function bolsillo()
    {
        return $this->belongsTo(Bolsillo::class);
    }

    public function sesionCaja()
    {
        return $this->belongsTo(SesionCaja::class, 'sesion_caja_id');
    }

    public function comprobanteEgreso()
    {
        return $this->belongsTo(ComprobanteEgreso::class);
    }

    public function comprobanteIngreso()
    {
        return $this->belongsTo(ComprobanteIngreso::class);
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopePorBolsillo(Builder $query, int $bolsilloId): void
    {
        $query->where('bolsillo_id', $bolsilloId);
    }

    public function scopePorTipo(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }
}
