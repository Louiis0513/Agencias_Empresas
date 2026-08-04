<?php

namespace App\Support;

use App\Models\TipoComprobante;

/**
 * Catálogo Siigo-like de tipos de comprobante contable (familia CC).
 */
final class CatalogoComprobantesContablesPredeterminados
{
    /**
     * @return list<array{
     *   familia: string,
     *   codigo: string,
     *   nombre: string,
     *   titulo: string,
     *   prefijo: string,
     *   libro_oficial: null
     * }>
     */
    public static function tipos(): array
    {
        $items = [
            ['1', 'Ajustes contables'],
            ['2', 'Depreciación'],
            ['3', 'Costeo'],
            ['4', 'Diferidos'],
            ['5', 'Legalización de viaticos'],
            ['6', 'Legalización de Caja menores'],
            ['7', 'Obligaciones financieras'],
            ['8', 'Nómina'],
            ['777', 'Ajustes contables de cartera'],
            ['ADT', 'Ajuste de tesorería'],
            ['992', 'Comprobante de nómina'],
            ['993', 'Comprobante de nómina provisión y seguridad social'],
            ['994', 'Comprobante liquidación de contrato'],
            ['995', 'Comprobante liquidación de primas'],
            ['996', 'Comprobante liquidación de cesantías'],
            ['997', 'Comprobante desembolso nómina'],
            ['998', 'Cierre año'],
            ['999', 'Saldos iniciales'],
            ['9901', 'Traslado de dinero'],
        ];

        $out = [];
        foreach ($items as [$codigo, $titulo]) {
            $out[] = [
                'familia' => TipoComprobante::FAMILIA_CC,
                'codigo' => $codigo,
                'nombre' => $titulo,
                'titulo' => $titulo,
                'prefijo' => 'CC',
                'libro_oficial' => null,
            ];
        }

        return $out;
    }
}
