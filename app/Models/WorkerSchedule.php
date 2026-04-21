<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerSchedule extends Model
{
    protected $table = 'worker_schedules';

    protected $fillable = [
        'store_id',
        'worker_id',
        'fecha_hora_entrada',
        'fecha_hora_salida',
        'es_festivo',
        'es_festivo2',
        'es_domingo',
        'no_compensa_semana_siguiente',
        'registered_by',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora_entrada' => 'datetime',
            'fecha_hora_salida' => 'datetime',
            'es_festivo' => 'boolean',
            'es_festivo2' => 'boolean',
            'es_domingo' => 'boolean',
            'no_compensa_semana_siguiente' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
