<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerceroDireccion extends Model
{
    public const TIPO_FISCAL = 'fiscal';

    public const TIPO_FACTURACION = 'facturacion';

    public const TIPO_ENTREGA = 'entrega';

    public const TIPO_CORRESPONDENCIA = 'correspondencia';

    public const TIPOS = [
        self::TIPO_FISCAL,
        self::TIPO_FACTURACION,
        self::TIPO_ENTREGA,
        self::TIPO_CORRESPONDENCIA,
    ];

    protected $table = 'tercero_direcciones';

    protected $fillable = [
        'tercero_id',
        'tipo',
        'linea',
        'ciudad',
        'departamento',
        'pais',
        'es_principal',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'tercero_id');
    }
}
