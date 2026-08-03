<?php

namespace App\Support;

use App\Models\CuentaContable;
use App\Models\Impuesto;

/**
 * Catálogo Siigo-like de impuestos + padres PUC del subárbol necesario.
 * Nombres de padres alineados a docs/cuentas-contables-puc.xlsx / puc.com.co.
 */
final class CatalogoImpuestosPredeterminados
{
    public const RELACION_VENTAS = 'Impuestos - Ventas';

    public const RELACION_COMPRAS = 'Impuestos - Compras';

    public const RELACION_DEV_VENTAS = 'Impuestos - Devolución ventas';

    public const RELACION_DEV_COMPRAS = 'Impuestos - Devolución compras';

    /**
     * Ancestros estructurales (1/2/4/6) con nombre PUC.
     *
     * @return array<string, string> codigo => nombre
     */
    public static function padresPuc(): array
    {
        return [
            '1' => 'Activo',
            '2' => 'Pasivo',
            '13' => 'Deudores comerciales y otras cuentas por cobrar',
            '23' => 'Acreedores comerciales y otras cuentas por pagar',
            '24' => 'Pasivos por impuestos',
            '1355' => 'Anticipo de impuestos y contribuciones o',
            '2365' => 'Retenciones en la fuente',
            '2367' => 'Impuesto a las ventas retenido',
            '2368' => 'Impuesto de industria y comercio retenido',
            '2408' => 'Impuesto sobre las ventas por pagar',
            '2464' => 'De licores, cervezas y cigarrillos',
            '2495' => 'Otros',
            '135515' => 'Anticipo Retención en la fuente',
            '135517' => 'Impuesto a las ventas retenido',
            '135518' => 'Impuesto de industria y comercio retenido',
            '236515' => 'Honorarios',
            '236520' => 'Comisiones',
            '236525' => 'Servicios',
            '236535' => 'Rendimientos financieros',
            '236540' => 'Retención por compras',
            '236570' => 'Otras retenciones',
            '236701' => 'Impuesto a las ventas retenido',
            '236805' => 'Retención industria y comercio Ica',
            '240806' => 'IVA generado',
            '240810' => 'IVA descontable por compras',
            '240820' => 'Descontable por devoluciones',
            '249501' => 'Impuesto al consumo nacional',
        ];
    }

    /**
     * @return list<array{
     *   codigo: int,
     *   nombre: string,
     *   tipo: string,
     *   tarifa: float,
     *   por_valor: bool,
     *   ventas: array{codigo: string, nombre: string, relacion_con: string, categoria: string},
     *   compras: array{codigo: string, nombre: string, relacion_con: string, categoria: string},
     *   devolucion_ventas: array{codigo: string, nombre: string, relacion_con: string, categoria: string},
     *   devolucion_compras: array{codigo: string, nombre: string, relacion_con: string, categoria: string}
     * }>
     */
    public static function impuestos(): array
    {
        $cxc = 'Cuentas por cobrar';
        $cxp = 'Cuentas por pagar';
        $otrosPasivos = 'Otros pasivos';

        $hoja = static function (
            string $codigo,
            string $nombre,
            string $relacion,
            string $categoria
        ): array {
            return [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'relacion_con' => $relacion,
                'categoria' => $categoria,
            ];
        };

        return [
            [
                'codigo' => 1,
                'nombre' => 'IVA 19%',
                'tipo' => Impuesto::TIPO_IVA,
                'tarifa' => 19,
                'por_valor' => false,
                'ventas' => $hoja('24080601', 'IVA generado 19%', self::RELACION_VENTAS, $otrosPasivos),
                'compras' => $hoja('24081001', 'IVA descontable por compras 19%', self::RELACION_COMPRAS, $otrosPasivos),
                'devolucion_ventas' => $hoja('24082001', 'Descontable por devoluciones 19%', self::RELACION_DEV_VENTAS, $otrosPasivos),
                'devolucion_compras' => $hoja('24081002', 'IVA devolución en compras 19%', self::RELACION_DEV_COMPRAS, $otrosPasivos),
            ],
            [
                'codigo' => 2,
                'nombre' => 'IVA 5%',
                'tipo' => Impuesto::TIPO_IVA,
                'tarifa' => 5,
                'por_valor' => false,
                'ventas' => $hoja('24080602', 'IVA generado 5%', self::RELACION_VENTAS, $otrosPasivos),
                'compras' => $hoja('24081003', 'IVA descontable por compras 5%', self::RELACION_COMPRAS, $otrosPasivos),
                'devolucion_ventas' => $hoja('24082002', 'Descontable por devoluciones 5%', self::RELACION_DEV_VENTAS, $otrosPasivos),
                'devolucion_compras' => $hoja('24081004', 'IVA devolución en compras 5%', self::RELACION_DEV_COMPRAS, $otrosPasivos),
            ],
            [
                'codigo' => 3,
                'nombre' => 'Retefuente 11%',
                'tipo' => Impuesto::TIPO_RETEFUENTE,
                'tarifa' => 11,
                'por_valor' => false,
                'ventas' => $hoja('13551509', 'Anticipo retención en la fuente 11%', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23651501', 'Honorarios', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551510', 'Devolución retención en la fuente 11%', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23651502', 'Devolución honorarios', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 4,
                'nombre' => 'Retefuente 10%',
                'tipo' => Impuesto::TIPO_RETEFUENTE,
                'tarifa' => 10,
                'por_valor' => false,
                'ventas' => $hoja('13551507', 'Anticipo retención en la fuente 10%', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23652001', 'Comisiones', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551508', 'Devolución retención en la fuente 10%', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23652002', 'Devolución comisiones', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 5,
                'nombre' => 'Retefuente 6%',
                'tipo' => Impuesto::TIPO_RETEFUENTE,
                'tarifa' => 6,
                'por_valor' => false,
                'ventas' => $hoja('13551505', 'Anticipo retención en la fuente 6%', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23652501', 'Servicios 6%', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551506', 'Devolución retención en la fuente 6%', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23652502', 'Devolución servicios 6%', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 6,
                'nombre' => 'Retefuente 4%',
                'tipo' => Impuesto::TIPO_RETEFUENTE,
                'tarifa' => 4,
                'por_valor' => false,
                'ventas' => $hoja('13551503', 'Anticipo retención en la fuente 4%', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23652503', 'Servicios 4%', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551504', 'Devolución retención en la fuente 4%', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23652504', 'Devolución servicios 4%', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 7,
                'nombre' => 'Retefuente 2.5%',
                'tipo' => Impuesto::TIPO_RETEFUENTE,
                'tarifa' => 2.5,
                'por_valor' => false,
                'ventas' => $hoja('13551501', 'Anticipo retención en la fuente 2.5%', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23654001', 'Retención por compras 2.5%', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551502', 'Devolución retención en la fuente 2.5%', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23654002', 'Devolución retención por compras 2.5%', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 8,
                'nombre' => 'ReteICA 11.04',
                'tipo' => Impuesto::TIPO_RETEICA,
                'tarifa' => 11.04,
                'por_valor' => false,
                'ventas' => $hoja('13551801', 'ReteICA 11.04', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23680501', 'ReteICA 11.04', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551802', 'Devolución ReteICA 11.04', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23680502', 'Devolución ReteICA 11.04', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 9,
                'nombre' => 'ReteICA 13.8',
                'tipo' => Impuesto::TIPO_RETEICA,
                'tarifa' => 13.8,
                'por_valor' => false,
                'ventas' => $hoja('13551803', 'ReteICA 13.8', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23680503', 'ReteICA 13.8', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551804', 'Devolución ReteICA 13.8', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23680504', 'Devolución ReteICA 13.8', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 10,
                'nombre' => 'ReteICA 9.66',
                'tipo' => Impuesto::TIPO_RETEICA,
                'tarifa' => 9.66,
                'por_valor' => false,
                'ventas' => $hoja('13551805', 'ReteICA 9.66', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23680505', 'ReteICA 9.66', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551806', 'Devolución ReteICA 9.66', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23680506', 'Devolución ReteICA 9.66', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 11,
                'nombre' => 'ReteICA 8',
                'tipo' => Impuesto::TIPO_RETEICA,
                'tarifa' => 8,
                'por_valor' => false,
                'ventas' => $hoja('13551807', 'ReteICA 8', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23680507', 'ReteICA 8', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551808', 'Devolución ReteICA 8', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23680508', 'Devolución ReteICA 8', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 12,
                'nombre' => 'ReteICA 7',
                'tipo' => Impuesto::TIPO_RETEICA,
                'tarifa' => 7,
                'por_valor' => false,
                'ventas' => $hoja('13551809', 'ReteICA 7', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23680509', 'ReteICA 7', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551810', 'Devolución ReteICA 7', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23680510', 'Devolución ReteICA 7', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 13,
                'nombre' => 'ReteICA 6.9',
                'tipo' => Impuesto::TIPO_RETEICA,
                'tarifa' => 6.9,
                'por_valor' => false,
                'ventas' => $hoja('13551811', 'ReteICA 6.9', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23680511', 'ReteICA 6.9', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551812', 'Devolución ReteICA 6.9', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23680512', 'Devolución ReteICA 6.9', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 14,
                'nombre' => 'ReteICA 4.14',
                'tipo' => Impuesto::TIPO_RETEICA,
                'tarifa' => 4.14,
                'por_valor' => false,
                'ventas' => $hoja('13551813', 'ReteICA 4.14', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23680513', 'ReteICA 4.14', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551814', 'Devolución ReteICA 4.14', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23680514', 'Devolución ReteICA 4.14', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 15,
                'nombre' => 'ReteIVA 15%',
                'tipo' => Impuesto::TIPO_RETEIVA,
                'tarifa' => 15,
                'por_valor' => false,
                'ventas' => $hoja('13551701', 'Impuesto a las ventas retenido 15%', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23670101', 'Impuesto a las ventas retenido 15%', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551702', 'Devolución impuesto a las ventas retenido 15%', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23670102', 'Devolución impuesto a las ventas retenido 15%', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 16,
                'nombre' => 'Impoconsumo 8%',
                'tipo' => Impuesto::TIPO_IMPOCONSUMO,
                'tarifa' => 8,
                'por_valor' => false,
                'ventas' => $hoja('24950101', 'Impuesto al consumo en ventas', self::RELACION_VENTAS, $otrosPasivos),
                'compras' => $hoja('24950103', 'Impuesto al consumo en compras', self::RELACION_COMPRAS, $otrosPasivos),
                'devolucion_ventas' => $hoja('24950102', 'Impuesto al consumo en devolución en ventas', self::RELACION_DEV_VENTAS, $otrosPasivos),
                'devolucion_compras' => $hoja('24950104', 'Impuesto al consumo en devolución en compras', self::RELACION_DEV_COMPRAS, $otrosPasivos),
            ],
            [
                'codigo' => 17,
                'nombre' => 'Impoconsumo por valor',
                'tipo' => Impuesto::TIPO_IMPOCONSUMO,
                'tarifa' => 0,
                'por_valor' => true,
                'ventas' => $hoja('246401', 'Impuesto por valor en ventas', self::RELACION_VENTAS, $otrosPasivos),
                'compras' => $hoja('246403', 'Impuesto por valor en compras', self::RELACION_COMPRAS, $otrosPasivos),
                'devolucion_ventas' => $hoja('246402', 'Impuesto por valor en devolución en ventas', self::RELACION_DEV_VENTAS, $otrosPasivos),
                'devolucion_compras' => $hoja('246404', 'Impuesto por valor en devolución en compras', self::RELACION_DEV_COMPRAS, $otrosPasivos),
            ],
            [
                'codigo' => 18,
                'nombre' => 'Retefuente 3.5%',
                'tipo' => Impuesto::TIPO_RETEFUENTE,
                'tarifa' => 3.5,
                'por_valor' => false,
                'ventas' => $hoja('13551513', 'Anticipo retención en la fuente 3.5%', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23654004', 'Retención por compras 3.5%', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551514', 'Devolución retención en la fuente 3.5%', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23654005', 'Devolución retención por compras 3.5%', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 19,
                'nombre' => 'Retefuente 7%',
                'tipo' => Impuesto::TIPO_RETEFUENTE,
                'tarifa' => 7,
                'por_valor' => false,
                'ventas' => $hoja('13551511', 'Anticipo retención en la fuente 7%', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23653502', 'Rendimientos financieros 7%', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551512', 'Devolución retención en la fuente 7%', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23653503', 'Devolución rendimientos financieros 7%', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 20,
                'nombre' => 'Retefuente 2%',
                'tipo' => Impuesto::TIPO_RETEFUENTE,
                'tarifa' => 2,
                'por_valor' => false,
                'ventas' => $hoja('13551515', 'Anticipo retención en la fuente 2%', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23657001', 'Otras retenciones 2%', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551516', 'Devolución retención en la fuente 2%', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23657002', 'Devolución otras retenciones 2%', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 21,
                'nombre' => 'Retefuente 1%',
                'tipo' => Impuesto::TIPO_RETEFUENTE,
                'tarifa' => 1,
                'por_valor' => false,
                'ventas' => $hoja('13551517', 'Anticipo retención en la fuente 1%', self::RELACION_VENTAS, $cxc),
                'compras' => $hoja('23652505', 'Servicios 1%', self::RELACION_COMPRAS, $cxp),
                'devolucion_ventas' => $hoja('13551518', 'Devolución retención en la fuente 1%', self::RELACION_DEV_VENTAS, $cxc),
                'devolucion_compras' => $hoja('23652506', 'Devolución servicios 1%', self::RELACION_DEV_COMPRAS, $cxp),
            ],
            [
                'codigo' => 22,
                'nombre' => 'IVA 0%',
                'tipo' => Impuesto::TIPO_IVA,
                'tarifa' => 0,
                'por_valor' => false,
                // Reutiliza cuentas del IVA 19% (estilo Siigo).
                'ventas' => $hoja('24080601', 'IVA generado 19%', self::RELACION_VENTAS, $otrosPasivos),
                'compras' => $hoja('24081001', 'IVA descontable por compras 19%', self::RELACION_COMPRAS, $otrosPasivos),
                'devolucion_ventas' => $hoja('24082001', 'Descontable por devoluciones 19%', self::RELACION_DEV_VENTAS, $otrosPasivos),
                'devolucion_compras' => $hoja('24081002', 'IVA devolución en compras 19%', self::RELACION_DEV_COMPRAS, $otrosPasivos),
            ],
        ];
    }

    /**
     * Códigos hoja de 6 dígitos que en Siigo son transaccionales (Impoconsumo por valor).
     *
     * @return list<string>
     */
    public static function hojasSeisDigitosTransaccionales(): array
    {
        return ['246401', '246402', '246403', '246404'];
    }

    public static function nombrePadre(string $codigo): ?string
    {
        return self::padresPuc()[$codigo] ?? null;
    }

    public static function esHojaSeisTransaccional(string $codigo): bool
    {
        return in_array($codigo, self::hojasSeisDigitosTransaccionales(), true);
    }

    /**
     * Prefijos de la cadena PUC para un código hoja (1→2→4→6→8…).
     *
     * @return list<string>
     */
    public static function cadenaCodigos(string $codigo): array
    {
        $digitos = preg_replace('/\D/', '', $codigo) ?? '';
        $cadena = [];
        foreach ([1, 2, 4, 6, 8, 10] as $len) {
            if (strlen($digitos) >= $len) {
                $cadena[] = substr($digitos, 0, $len);
            }
        }

        return $cadena;
    }

    public static function claseDesdeCodigo(string $codigo): string
    {
        return CuentaContable::claseDesdeCodigo($codigo) ?? 'Activo';
    }
}
