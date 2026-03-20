<?php

namespace Tests\Unit;

use App\Services\ProductService;
use Tests\TestCase;

class ProductServiceMarginTest extends TestCase
{
    public function test_calcula_precio_desde_costo_y_margen(): void
    {
        $service = app(ProductService::class);

        $result = $service->resolvePriceAndMargin(8000.0, null, 20, 'COP');

        $this->assertSame(10000.0, $result['price']);
        $this->assertSame(20.0, $result['margin']);
    }

    public function test_calcula_margen_desde_costo_y_precio(): void
    {
        $service = app(ProductService::class);

        $result = $service->resolvePriceAndMargin(8000.0, 10000.0, null, 'COP');

        $this->assertSame(10000.0, $result['price']);
        $this->assertSame(20.0, $result['margin']);
    }

    public function test_falla_si_llegan_precio_y_margen(): void
    {
        $service = app(ProductService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ingresa precio o margen, no ambos.');

        $service->resolvePriceAndMargin(8000.0, 10000.0, 20.0, 'COP');
    }

    public function test_falla_si_no_llega_precio_ni_margen(): void
    {
        $service = app(ProductService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ingresa precio o margen, no ambos.');

        $service->resolvePriceAndMargin(8000.0, null, null, 'COP');
    }
}
