<?php

namespace App\Support;

use App\Models\UnidadMedidaFe;
use Illuminate\Support\Facades\Schema;

/**
 * Acceso al catálogo DIAN de unidades de medida FE.
 * Preferir siempre la tabla unidades_medida_fe; fallback mínimo si aún no está sembrada.
 */
class CatalogoUnidadesMedidaDian
{
    /** Fallback si la tabla está vacía / no migrada. */
    private const FALLBACK = [
        '94' => 'unidad',
    ];

    /**
     * @return array<string, string> codigo => nombre
     */
    public static function opciones(): array
    {
        if (! Schema::hasTable('unidades_medida_fe')) {
            return self::FALLBACK;
        }

        $opciones = UnidadMedidaFe::query()
            ->ordenadas()
            ->pluck('nombre', 'codigo')
            ->all();

        return $opciones !== [] ? $opciones : self::FALLBACK;
    }

    public static function etiqueta(string $codigo): string
    {
        $opciones = self::opciones();
        $nombre = $opciones[$codigo] ?? $codigo;

        return $codigo.' - '.$nombre;
    }
}
