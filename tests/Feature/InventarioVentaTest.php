<?php

namespace Tests\Feature;

use App\Models\BitacoraMovimiento;
use App\Models\Inventario;
use App\Models\Sede;
use App\Models\User;
use App\Models\Variante;
use App\Models\Venta;
use App\Services\AlertaService;
use App\Services\InventarioService;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarioVentaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ModaExpressSeeder::class);
        config(['services.ecommerce.key' => 'test-ecommerce-key-12345']);
    }

    public function test_calculo_disponibilidad_real(): void
    {
        $inventarioService = app(InventarioService::class);

        $inv = new Inventario([
            'stock_fisico' => 20,
            'stock_reservado' => 4,
            'stock_comprometido' => 2,
        ]);

        $this->assertEquals(14, $inventarioService->calcularDisponibilidad($inv));
    }

    /**
     * Prueba Requerimiento 2: Venta atómica descuenta stock y registra bitácora
     */
    public function test_venta_atomica_y_bitacora(): void
    {
        $ventaService = app(VentaService::class);
        $sede = Sede::first();
        $variante = Variante::first();

        $inventario = Inventario::where('sede_id', $sede->id)
            ->where('variante_id', $variante->id)
            ->first();

        $stockInicial = $inventario->stock_fisico;

        $venta = $ventaService->confirmarVenta(
            datosVenta: [
                'sede_id' => $sede->id,
                'canal' => 'fisico',
                'metodo_pago' => 'efectivo',
            ],
            items: [
                [
                    'variante_id' => $variante->id,
                    'cantidad' => 2,
                ]
            ]
        );

        $this->assertInstanceOf(Venta::class, $venta);
        $this->assertEquals('completada', $venta->estado);

        $inventario->refresh();
        $this->assertEquals($stockInicial - 2, $inventario->stock_fisico);

        // Bitácora registrada
        $bitacora = BitacoraMovimiento::where('referencia_tipo', Venta::class)
            ->where('referencia_id', $venta->id)
            ->latest()
            ->first();

        $this->assertNotNull($bitacora);
        $this->assertEquals(-2, $bitacora->cantidad);
        $this->assertEquals($stockInicial, $bitacora->stock_fisico_anterior);
        $this->assertEquals($stockInicial - 2, $bitacora->stock_fisico_nuevo);
    }

    /**
     * Prueba Requerimiento 2b: Rollback si no hay stock suficiente
     */
    public function test_venta_hace_rollback_si_stock_insuficiente(): void
    {
        $ventaService = app(VentaService::class);
        $sede = Sede::first();
        $variante = Variante::first();

        $inventario = Inventario::where('sede_id', $sede->id)
            ->where('variante_id', $variante->id)
            ->first();

        $stockInicial = $inventario->stock_fisico;
        $ventasIniciales = Venta::count();

        $this->expectException(\Exception::class);

        $ventaService->confirmarVenta(
            datosVenta: [
                'sede_id' => $sede->id,
                'canal' => 'fisico',
                'metodo_pago' => 'efectivo',
            ],
            items: [
                [
                    'variante_id' => $variante->id,
                    'cantidad' => $stockInicial + 9999, // Supera stock disponible
                ]
            ]
        );

        $inventario->refresh();
        $this->assertEquals($stockInicial, $inventario->stock_fisico);
        $this->assertEquals($ventasIniciales, Venta::count());
    }

    /**
     * Prueba Requerimiento 4: Sugerencias de redistribución de tiendas con sobrestock
     */
    public function test_sugerencias_redistribucion_sobrestock(): void
    {
        $alertaService = app(AlertaService::class);
        $variante = Variante::where('sku', 'POL-PIM-NEG-M')->first();
        $sedeMiraflores = Sede::where('codigo', 'TIENDA-01')->first();

        $res = $alertaService->obtenerSugerenciasTransferencia($sedeMiraflores->id, $variante->id);

        $this->assertTrue($res['success']);
        $this->assertTrue($res['tiene_excedente_externo']);
        $this->assertNotEmpty($res['sugerencias']);
        // San Isidro debe tener disponible > maximo
        $this->assertGreaterThan(0, $res['sugerencias'][0]['unidades_excedentes']);
    }

    /**
     * Prueba Rutas de Interfaz (Configuración de Umbrales con Autenticación)
     */
    public function test_interfaz_umbrales_render_y_actualizacion(): void
    {
        $gerente = User::where('email', 'gerente@modaexpress.com')->first();

        $response = $this->actingAs($gerente)->get(route('inventario.umbrales'));
        $response->assertStatus(200);
        $response->assertSee('Umbrales de stock');

        $inventario = Inventario::where('sede_id', $gerente->sede_id)->first();
        $responsePut = $this->actingAs($gerente)->put(route('inventario.umbral.update', $inventario->id), [
            'stock_minimo' => 8,
            'stock_objetivo' => 20,
            'stock_maximo' => 40,
        ]);
        $responsePut->assertSessionHasNoErrors();
        $inventario->refresh();
        $this->assertEquals(8, $inventario->stock_minimo);
        $this->assertEquals(20, $inventario->stock_objetivo);
        $this->assertEquals(40, $inventario->stock_maximo);
    }

    /**
     * Prueba Control de Acceso por Rol (Vendedor no puede editar umbrales)
     */
    public function test_vendedor_no_puede_acceder_a_umbrales(): void
    {
        $cajero = User::where('email', 'cajero@modaexpress.com')->first();
        $response = $this->actingAs($cajero)->get(route('inventario.umbrales'));
        $response->assertStatus(403);
    }

    /**
     * Prueba Rutas de Interfaz 2 (Panel de Alertas y Fetch API)
     */
    public function test_interfaz_panel_alertas_y_api_sugerencias(): void
    {
        $gerente = User::where('email', 'gerente@modaexpress.com')->first();

        $response = $this->actingAs($gerente)->get(route('alertas.index'));
        $response->assertStatus(200);
        $response->assertSee('Alertas de stock');

        $variante = Variante::where('sku', 'POL-PIM-NEG-M')->first();
        $responseApi = $this->actingAs($gerente)->getJson(route('alertas.sugerencias', [
            'varianteId' => $variante->id,
            'sede_id' => $gerente->sede_id,
        ]));

        $responseApi->assertStatus(200);
        $responseApi->assertJsonStructure([
            'success',
            'variante' => ['id', 'sku', 'nombre', 'stock_local_disponible'],
            'tiene_excedente_externo',
            'sugerencias',
        ]);
    }

    /**
     * Prueba POS: Búsqueda por código de barras y confirmación de venta
     */
    public function test_pos_busqueda_codigo_y_confirmacion_venta(): void
    {
        $cajero = User::where('email', 'cajero@modaexpress.com')->first();
        $variante = Variante::where('sku', 'POL-PIM-NEG-S')->first();

        // 1. Render POS
        $resPos = $this->actingAs($cajero)->get(route('ventas.pos'));
        $resPos->assertStatus(200);
        $resPos->assertSee('Punto de venta');

        // 2. Buscar código de barras
        $resBuscar = $this->actingAs($cajero)->getJson(route('ventas.buscar_codigo', [
            'codigo' => $variante->codigo_barra,
            'sede_id' => $cajero->sede_id,
        ]));
        $resBuscar->assertStatus(200);
        $resBuscar->assertJsonPath('data.sku', $variante->sku);

        // 3. Confirmar venta física
        $resVenta = $this->actingAs($cajero)->postJson(route('ventas.store'), [
            'sede_id' => $cajero->sede_id,
            'metodo_pago' => 'tarjeta',
            'items' => [
                [
                    'variante_id' => $variante->id,
                    'cantidad' => 1,
                ]
            ],
        ]);
        $resVenta->assertStatus(201);
        $resVenta->assertJsonPath('success', true);
    }

    /**
     * Prueba E-commerce API con autenticación por clave de API
     */
    public function test_api_ecommerce_venta_online_con_api_key(): void
    {
        $sedeOnline = Sede::where('tipo', 'online')->first();
        $variante = Variante::first();

        // Sin API Key -> 401
        $resSinKey = $this->postJson(route('api.ventas.online'), [
            'sede_id' => $sedeOnline->id,
            'metodo_pago' => 'pasarela',
            'items' => [
                ['variante_id' => $variante->id, 'cantidad' => 1]
            ]
        ]);
        $resSinKey->assertStatus(401);

        // Con API Key válida -> 201
        $resConKey = $this->withHeaders([
            'X-Api-Key' => 'test-ecommerce-key-12345'
        ])->postJson(route('api.ventas.online'), [
            'sede_id' => $sedeOnline->id,
            'metodo_pago' => 'pasarela',
            'items' => [
                ['variante_id' => $variante->id, 'cantidad' => 1]
            ]
        ]);
        $resConKey->assertStatus(201);
        $resConKey->assertJsonPath('success', true);
        $resConKey->assertJsonPath('data.canal', 'online');
    }

    /**
     * Prueba Vista de Stock y Movimientos
     */
    public function test_vistas_stock_y_movimientos(): void
    {
        $gerente = User::where('email', 'gerente@modaexpress.com')->first();

        $resStock = $this->actingAs($gerente)->get(route('stock.index'));
        $resStock->assertStatus(200);
        $resStock->assertSee('Stock de la tienda');

        $resMov = $this->actingAs($gerente)->get(route('movimientos.index'));
        $resMov->assertStatus(200);
        $resMov->assertSee('Bitácora de movimientos');
    }

    /**
     * Prueba POS: Generación de Factura con RUC y visualización del comprobante imprimible
     */
    public function test_pos_genera_boleta_y_factura_con_comprobante(): void
    {
        $cajero = User::where('email', 'cajero@modaexpress.com')->first();
        $variante = Variante::where('sku', 'POL-PIM-NEG-S')->first();

        // 1. Factura sin RUC debe fallar validación (422)
        $resInvalida = $this->actingAs($cajero)->postJson(route('ventas.store'), [
            'sede_id' => $cajero->sede_id,
            'tipo_comprobante' => 'factura',
            'metodo_pago' => 'efectivo',
            'items' => [['variante_id' => $variante->id, 'cantidad' => 1]],
        ]);
        $resInvalida->assertStatus(422);

        // 2. Factura válida con RUC (11 dígitos) y Razón Social -> prefijo F
        $resFactura = $this->actingAs($cajero)->postJson(route('ventas.store'), [
            'sede_id' => $cajero->sede_id,
            'tipo_comprobante' => 'factura',
            'cliente_documento' => '20608945123',
            'cliente_nombre' => 'Comercial Textil Peruana S.A.C.',
            'cliente_direccion' => 'Av. Industrial 540, Lima',
            'metodo_pago' => 'tarjeta',
            'items' => [['variante_id' => $variante->id, 'cantidad' => 2]],
        ]);

        $resFactura->assertStatus(201);
        $resFactura->assertJsonPath('venta.tipo_comprobante', 'factura');
        $resFactura->assertJsonPath('venta.cliente.documento', '20608945123');
        $this->assertStringStartsWith('F', $resFactura->json('venta.codigo'));

        // 3. Ver comprobante imprimible
        $ventaId = $resFactura->json('venta.id');
        $resComp = $this->actingAs($cajero)->get(route('ventas.comprobante', $ventaId));
        $resComp->assertStatus(200);
        $resComp->assertSee('FACTURA DE VENTA ELECTRÓNICA');
        $resComp->assertSee('Comercial Textil Peruana S.A.C.');
        $resComp->assertSee('20608945123');
    }

    /**
     * Prueba Módulo de Ingreso de Mercadería: mercadería existente y nueva mercadería con nuevo SKU
     */
    public function test_modulo_ingreso_mercaderia_existente_y_nueva(): void
    {
        $gerente = User::where('email', 'gerente@modaexpress.com')->first();
        $variante = Variante::where('sku', 'POL-PIM-NEG-M')->first();

        $inventario = Inventario::where('sede_id', $gerente->sede_id)
            ->where('variante_id', $variante->id)
            ->first();
        $stockAntes = $inventario->stock_fisico;

        // 1. Render vista de ingreso
        $resVista = $this->actingAs($gerente)->get(route('inventario.ingreso'));
        $resVista->assertStatus(200);
        $resVista->assertSee('Ingreso de mercadería');

        // 2. Ingresar cantidad a mercadería existente
        $resExistente = $this->actingAs($gerente)->post(route('inventario.ingreso.existente'), [
            'sede_id' => $gerente->sede_id,
            'variante_id' => $variante->id,
            'cantidad' => 15,
            'motivo' => 'Guía GR01-00120',
        ]);
        $resExistente->assertRedirect();
        $resExistente->assertSessionHasNoErrors();

        $inventario->refresh();
        $this->assertEquals($stockAntes + 15, $inventario->stock_fisico);

        // 3. Registrar nueva mercadería creando nuevo modelo y nuevo SKU
        $resNueva = $this->actingAs($gerente)->post(route('inventario.ingreso.nueva'), [
            'sede_id' => $gerente->sede_id,
            'modo_producto' => 'nuevo',
            'codigo_modelo' => 'CAS-DNM',
            'nombre_producto' => 'Casaca Denim Clásica',
            'categoria' => 'Casacas',
            'descripcion' => 'Denim 100% algodón',
            'sku' => 'CAS-DNM-AZU-M',
            'codigo_barra' => '775009990001',
            'talla' => 'M',
            'color' => 'Azul Índigo',
            'precio' => 179.90,
            'cantidad' => 25,
            'stock_minimo' => 5,
            'stock_objetivo' => 15,
            'stock_maximo' => 35,
            'motivo' => 'Lote inicial de temporada',
        ]);
        $resNueva->assertRedirect();
        $resNueva->assertSessionHasNoErrors();

        $nuevaVariante = Variante::where('sku', 'CAS-DNM-AZU-M')->first();
        $this->assertNotNull($nuevaVariante);
        $this->assertEquals('Casaca Denim Clásica', $nuevaVariante->producto->nombre);

        $invNuevo = Inventario::where('sede_id', $gerente->sede_id)
            ->where('variante_id', $nuevaVariante->id)
            ->first();
        $this->assertNotNull($invNuevo);
        $this->assertEquals(25, $invNuevo->stock_fisico);
    }
}
