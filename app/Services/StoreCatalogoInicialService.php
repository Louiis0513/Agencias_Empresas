<?php

namespace App\Services;

use App\Models\Store;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos mínimos por tienda (PUC, impuestos, formas de pago, categorías,
 * tipos de comprobante, centro de costo, listas de precio).
 * Idempotente: seguro al crear tienda y al re-ejecutar en seeders.
 */
class StoreCatalogoInicialService
{
    public function __construct(
        protected ImportacionPucService $importacionPucService,
        protected ImpuestoService $impuestoService,
        protected FormaPagoService $formaPagoService,
        protected CategoriaContableService $categoriaContableService,
        protected DocumentoInventarioService $documentoInventarioService,
        protected TipoComprobanteService $tipoComprobanteService,
        protected CentroCostoService $centroCostoService,
        protected ListaPrecioService $listaPrecioService,
    ) {}

    public function bootstrap(Store $store): void
    {
        DB::transaction(function () use ($store) {
            $this->importacionPucService->importarDesdeExcel($store);

            $this->assertSinErrores(
                'Impuestos',
                $this->impuestoService->asegurarDefaults($store)
            );
            $this->assertSinErrores(
                'Formas de pago',
                $this->formaPagoService->asegurarDefaults($store)
            );
            $this->assertSinErrores(
                'Categorías contables',
                $this->categoriaContableService->asegurarCategoriasPorDefecto($store)
            );

            $this->documentoInventarioService->asegurarCuentaPuenteSaldosIniciales($store);

            $this->assertSinErrores(
                'Tipos de comprobante',
                $this->tipoComprobanteService->asegurarTiposPorDefecto($store)
            );

            $this->centroCostoService->asegurarDefaults($store);
            $this->listaPrecioService->asegurarListasPorDefecto($store);
        });
    }

    /**
     * @param  array{errores?: list<string>}  $stats
     */
    private function assertSinErrores(string $paso, array $stats): void
    {
        $errores = $stats['errores'] ?? [];
        if ($errores === []) {
            return;
        }

        throw new Exception($paso.': '.implode(' ', $errores));
    }
}
