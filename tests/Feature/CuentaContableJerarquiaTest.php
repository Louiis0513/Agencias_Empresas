<?php

namespace Tests\Feature;

use App\Models\ComprobanteContable;
use App\Models\CuentaContable;
use App\Models\FormaPago;
use App\Models\MovimientoContable;
use App\Models\Store;
use App\Models\TipoComprobante;
use App\Models\User;
use App\Services\AsientoContableService;
use App\Services\CuentaContableService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CuentaContableJerarquiaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->create(['user_id' => $this->user->id]);
        DB::table('store_user')->insert([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'role_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_crea_jerarquia_completa_hasta_subauxiliar(): void
    {
        $servicio = $this->servicio();

        $clase = $this->crearEstructura($this->store, '1', 'Activo', false);

        $grupo = $servicio->crearHijo($this->store, [
            'cuenta_padre_id' => $clase->id,
            'sufijo' => '1',
            'nombre' => 'Disponible',
        ])['cuenta'];
        $this->assertSame('11', $grupo->codigo);
        $this->assertFalse($grupo->es_auxiliar);
        $this->assertNull($grupo->nivel_agrupacion);

        $cuenta = $servicio->crearHijo($this->store, [
            'cuenta_padre_id' => $grupo->id,
            'sufijo' => '05',
            'nombre' => 'Caja',
        ])['cuenta'];
        $this->assertSame('1105', $cuenta->codigo);

        $subcuenta = $servicio->crearHijo($this->store, [
            'cuenta_padre_id' => $cuenta->id,
            'sufijo' => '05',
            'nombre' => 'Caja general',
        ])['cuenta'];
        $this->assertSame('110505', $subcuenta->codigo);
        $this->assertFalse($subcuenta->es_auxiliar);

        $auxiliar = $servicio->crearHijo($this->store, [
            'cuenta_padre_id' => $subcuenta->id,
            'sufijo' => '01',
            'nombre' => 'Caja principal',
            'nivel_agrupacion' => CuentaContable::NIVEL_TRANSACCIONAL,
            'omitir_bolsillo' => true,
        ])['cuenta'];
        $this->assertSame('11050501', $auxiliar->codigo);
        $this->assertTrue($auxiliar->es_auxiliar);
        $this->assertSame(CuentaContable::NIVEL_TRANSACCIONAL, $auxiliar->nivel_agrupacion);

        $subauxiliar = $servicio->crearHijo($this->store, [
            'cuenta_padre_id' => $auxiliar->id,
            'sufijo' => '01',
            'nombre' => 'Caja mostrador',
            'nivel_agrupacion' => CuentaContable::NIVEL_TRANSACCIONAL,
            'omitir_bolsillo' => true,
        ])['cuenta'];
        $this->assertSame('1105050101', $subauxiliar->codigo);
        $this->assertTrue($subauxiliar->es_auxiliar);
        $this->assertSame(CuentaContable::NIVEL_TRANSACCIONAL, $subauxiliar->nivel_agrupacion);
        $this->assertSame(10, CuentaContable::longitudCodigo($subauxiliar->codigo));
    }

    public function test_rechaza_hijo_bajo_subauxiliar_codigo_duplicado_y_sufijo_invalido(): void
    {
        $servicio = $this->servicio();
        $clase = $this->crearEstructura($this->store, '1', 'Activo', false);
        $grupo = $servicio->crearHijo($this->store, [
            'cuenta_padre_id' => $clase->id,
            'sufijo' => '1',
            'nombre' => 'Disponible',
        ])['cuenta'];
        $cuenta = $servicio->crearHijo($this->store, [
            'cuenta_padre_id' => $grupo->id,
            'sufijo' => '05',
            'nombre' => 'Caja',
        ])['cuenta'];
        $sub = $servicio->crearHijo($this->store, [
            'cuenta_padre_id' => $cuenta->id,
            'sufijo' => '05',
            'nombre' => 'Caja general',
        ])['cuenta'];
        $aux = $servicio->crearHijo($this->store, [
            'cuenta_padre_id' => $sub->id,
            'sufijo' => '01',
            'nombre' => 'Aux',
            'omitir_bolsillo' => true,
        ])['cuenta'];
        $subaux = $servicio->crearHijo($this->store, [
            'cuenta_padre_id' => $aux->id,
            'sufijo' => '01',
            'nombre' => 'Subaux',
            'omitir_bolsillo' => true,
        ])['cuenta'];

        try {
            $servicio->crearHijo($this->store, [
                'cuenta_padre_id' => $subaux->id,
                'sufijo' => '01',
                'nombre' => 'No debería',
            ]);
            $this->fail('Debió rechazar hijo bajo longitud 10');
        } catch (Exception $e) {
            $this->assertStringContainsString('No se puede crear un hijo', $e->getMessage());
        }

        try {
            $servicio->crearHijo($this->store, [
                'cuenta_padre_id' => $aux->id,
                'sufijo' => '01',
                'nombre' => 'Duplicado',
            ]);
            $this->fail('Debió rechazar código duplicado');
        } catch (Exception $e) {
            $this->assertStringContainsString('Ya existe la cuenta', $e->getMessage());
        }

        try {
            $servicio->crearHijo($this->store, [
                'cuenta_padre_id' => $aux->id,
                'sufijo' => '00',
                'nombre' => 'Sufijo cero',
            ]);
            $this->fail('Debió rechazar sufijo 00');
        } catch (Exception $e) {
            $this->assertStringContainsString('sufijo', strtolower($e->getMessage()));
        }
    }

    public function test_traslado_requiere_confirmacion_y_mueve_movimientos_y_forma_pago(): void
    {
        $servicio = $this->servicio();
        $this->crearCadenaHasta($this->store, '110505');
        $sub = CuentaContable::query()->deStore($this->store)->where('codigo', '110505')->firstOrFail();

        $auxiliar = $servicio->crearHijo($this->store, [
            'cuenta_padre_id' => $sub->id,
            'sufijo' => '01',
            'nombre' => 'Caja principal',
            'nivel_agrupacion' => CuentaContable::NIVEL_TRANSACCIONAL,
            'omitir_bolsillo' => true,
        ])['cuenta'];

        $gasto = $this->crearEstructura($this->store, '51959501', 'Gastos diversos', true);
        $tipo = TipoComprobante::create([
            'store_id' => $this->store->id,
            'familia' => TipoComprobante::FAMILIA_CC,
            'codigo' => '1',
            'nombre' => 'Comprobante contable',
            'titulo' => 'Comprobante contable',
            'prefijo' => 'CC',
            'numeracion_automatica' => true,
            'siguiente_numero' => 1,
            'activo' => true,
        ]);

        $comprobante = app(AsientoContableService::class)->crearBorrador($this->store, $this->user->id, [
            'tipo_comprobante_id' => $tipo->id,
            'fecha' => now()->toDateString(),
            'descripcion' => 'Gasto',
            'lineas' => [
                [
                    'cuenta_contable_id' => $gasto->id,
                    'debito' => 1000,
                    'credito' => 0,
                ],
                [
                    'cuenta_contable_id' => $auxiliar->id,
                    'debito' => 0,
                    'credito' => 1000,
                ],
            ],
        ]);
        app(AsientoContableService::class)->contabilizar($this->store, $comprobante, $this->user->id);

        $forma = FormaPago::create([
            'store_id' => $this->store->id,
            'en_uso' => true,
            'codigo' => 1,
            'nombre' => 'Efectivo',
            'aplica_a' => FormaPago::APLICA_AMBOS,
            'cuenta_contable_id' => $auxiliar->id,
            'medio_pago_dian' => '10',
            'es_pago_en_linea' => false,
        ]);

        $this->assertTrue($servicio->padreTieneUsos($auxiliar->fresh()));
        $meta = $servicio->metaCrearHijo($this->store, $auxiliar->fresh());
        $this->assertTrue($meta['tiene_usos']);

        try {
            $servicio->crearHijo($this->store, [
                'cuenta_padre_id' => $auxiliar->id,
                'sufijo' => '01',
                'nombre' => 'Caja mostrador',
                'omitir_bolsillo' => true,
                'confirmar_traslado' => false,
            ]);
            $this->fail('Debió exigir confirmar_traslado');
        } catch (Exception $e) {
            $this->assertStringContainsString('confirmar_traslado', $e->getMessage());
        }

        $resultado = $servicio->crearHijo($this->store, [
            'cuenta_padre_id' => $auxiliar->id,
            'sufijo' => '01',
            'nombre' => 'Caja mostrador',
            'omitir_bolsillo' => true,
            'confirmar_traslado' => true,
        ]);

        $this->assertTrue($resultado['traslado_realizado']);
        $hijo = $resultado['cuenta'];
        $this->assertSame('1105050101', $hijo->codigo);

        $auxiliar->refresh();
        $this->assertNull($auxiliar->nivel_agrupacion);

        $this->assertSame(
            $hijo->id,
            MovimientoContable::query()->where('cuenta_contable_id', $hijo->id)->value('cuenta_contable_id')
        );
        $this->assertSame(0, MovimientoContable::query()->where('cuenta_contable_id', $auxiliar->id)->count());
        $this->assertSame($hijo->id, $forma->fresh()->cuenta_contable_id);
        $this->assertSame(ComprobanteContable::ESTADO_CONTABILIZADO, $comprobante->fresh()->estado);
    }

    public function test_propietario_puede_crear_hijo_por_http(): void
    {
        $clase = $this->crearEstructura($this->store, '1', 'Activo', false);

        $response = $this->actingAs($this->user)
            ->post(route('stores.contabilidad.cuentas.hijos', $this->store), [
                'cuenta_padre_id' => $clase->id,
                'sufijo' => '1',
                'nombre' => 'Disponible',
            ]);

        $response->assertRedirect(route('stores.contabilidad.cuentas', $this->store));
        $this->assertDatabaseHas('cuentas_contables', [
            'store_id' => $this->store->id,
            'codigo' => '11',
            'nombre' => 'Disponible',
        ]);
    }

    public function test_arbol_index_y_hijos_json(): void
    {
        $this->crearEstructura($this->store, '1', 'Activo', false);
        $this->crearEstructura($this->store, '11', 'Disponible', false);
        $this->crearEstructura($this->store, '1105', 'Caja', false);

        $clase = CuentaContable::query()->deStore($this->store)->where('codigo', '1')->firstOrFail();

        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.cuentas', $this->store))
            ->assertOk()
            ->assertSee('Despliega cada nivel', false)
            ->assertSee('1', false);

        $json = $this->actingAs($this->user)
            ->getJson(route('stores.contabilidad.cuentas.hijos.json', [
                'store' => $this->store,
                'cuentaContable' => $clase,
            ]));

        $json->assertOk();
        $data = $json->json();
        $this->assertIsArray($data);
        $this->assertSame('11', $data[0]['codigo'] ?? null);
        $this->assertTrue($data[0]['tiene_hijos'] ?? false);
    }

    public function test_meta_crear_hijo_no_rompe_si_clase_llena_y_sugiere_hueco(): void
    {
        $servicio = $this->servicio();
        $clase = $this->crearEstructura($this->store, '1', 'Activo', false);

        foreach ([1, 2, 3, 4, 5, 6, 7, 9] as $suf) {
            $this->crearEstructura($this->store, '1'.$suf, 'Grupo 1'.$suf, false);
        }

        $metaConHueco = $servicio->metaCrearHijo($this->store, $clase);
        $this->assertTrue($metaConHueco['puede']);
        $this->assertSame('8', $metaConHueco['sufijo_sugerido']);

        $this->crearEstructura($this->store, '18', 'Grupo 18', false);
        $metaLlena = $servicio->metaCrearHijo($this->store, $clase);
        $this->assertFalse($metaLlena['puede']);
        $this->assertNull($metaLlena['sufijo_sugerido']);
        $this->assertNotNull($metaLlena['mensaje']);

        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.cuentas', $this->store))
            ->assertOk();
    }

    public function test_usos_catalogo_incluye_categorias_con_enlace(): void
    {
        $inventario = $this->crearEstructura($this->store, '14350101', 'Mercancía', true);
        $costo = $this->crearEstructura($this->store, '61350501', 'Costo mercancía', true);
        $ingreso = $this->crearEstructura($this->store, '41350101', 'Ingreso mercancía', true);
        $devolucion = $this->crearEstructura($this->store, '41750501', 'Devolución mercancía', true);

        \App\Models\CategoriaContable::create([
            'store_id' => $this->store->id,
            'codigo' => '1',
            'nombre' => 'Productos',
            'tipo' => \App\Models\CategoriaContable::TIPO_PRODUCTO,
            'cuenta_inventario_id' => $inventario->id,
            'cuenta_costo_id' => $costo->id,
            'cuenta_ingreso_id' => $ingreso->id,
            'cuenta_devolucion_id' => $devolucion->id,
            'activo' => true,
        ]);

        $usos = $this->servicio()->usosCatalogoPorCuentaIds($this->store, [
            $inventario->id,
            $costo->id,
            $ingreso->id,
            $devolucion->id,
        ]);

        $etiquetasIngreso = collect($usos[$ingreso->id] ?? [])->pluck('etiqueta')->all();
        $this->assertContains('Categorías de productos y servicios - Ventas', $etiquetasIngreso);

        $usoVentas = collect($usos[$ingreso->id])->firstWhere(
            'etiqueta',
            'Categorías de productos y servicios - Ventas'
        );
        $this->assertSame(
            route('stores.contabilidad.categorias', $this->store),
            $usoVentas['url']
        );

        $this->assertContains(
            'Categorías de productos y servicios - Inventario',
            collect($usos[$inventario->id] ?? [])->pluck('etiqueta')->all()
        );
        $this->assertContains(
            'Categorías de productos y servicios - Costo',
            collect($usos[$costo->id] ?? [])->pluck('etiqueta')->all()
        );
        $this->assertContains(
            'Categorías de productos y servicios - Devolución',
            collect($usos[$devolucion->id] ?? [])->pluck('etiqueta')->all()
        );
    }

    private function servicio(): CuentaContableService
    {
        return app(CuentaContableService::class);
    }

    private function crearEstructura(Store $store, string $codigo, string $nombre, bool $esAuxiliar): CuentaContable
    {
        return CuentaContable::create([
            'store_id' => $store->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'clase' => CuentaContable::claseDesdeCodigo($codigo),
            'activo' => true,
            'nivel_agrupacion' => $esAuxiliar ? CuentaContable::NIVEL_TRANSACCIONAL : null,
            'es_auxiliar' => $esAuxiliar,
            'origen' => $esAuxiliar ? CuentaContable::ORIGEN_MANUAL : CuentaContable::ORIGEN_PLANTILLA,
        ]);
    }

    private function crearCadenaHasta(Store $store, string $codigoFinal): void
    {
        $codigos = [];
        $len = strlen($codigoFinal);
        foreach ([1, 2, 4, 6] as $l) {
            if ($l <= $len) {
                $codigos[] = substr($codigoFinal, 0, $l);
            }
        }
        foreach ($codigos as $codigo) {
            if (! CuentaContable::query()->deStore($store)->where('codigo', $codigo)->exists()) {
                $this->crearEstructura($store, $codigo, 'Cuenta '.$codigo, false);
            }
        }
    }
}
