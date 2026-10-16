<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarUmbralRequest;
use App\Http\Requests\IngresarMercaderiaExistenteRequest;
use App\Http\Requests\RegistrarNuevaMercaderiaRequest;
use App\Models\BitacoraMovimiento;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Variante;
use App\Services\AlertaService;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventarioController extends Controller
{
    public function __construct(
        protected InventarioService $inventarioService,
        protected AlertaService $alertaService
    ) {}

    /**
     * Módulo de ingreso de mercadería: permite sumar stock a variantes existentes
     * o dar de alta una nueva mercadería con su SKU y toda la información necesaria.
     */
    public function ingreso(Request $request): View
    {
        $sede = $this->sedeDeTrabajo($request);
        $tab = $request->query('tab') === 'nueva' ? 'nueva' : 'existente';

        // Variantes activas con su stock actual en la sede seleccionada
        $variantes = Variante::with([
            'producto',
            'inventarios' => fn ($q) => $q->where('sede_id', $sede->id),
        ])
            ->where('activo', true)
            ->orderBy('sku')
            ->get()
            ->map(function (Variante $v) {
                $inv = $v->inventarios->first();

                return (object) [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'codigo_barra' => $v->codigo_barra,
                    'producto' => $v->producto?->nombre ?? 'Prenda',
                    'categoria' => $v->producto?->categoria ?? 'General',
                    'talla' => $v->talla,
                    'color' => $v->color,
                    'precio' => (float) $v->precio,
                    'stock_fisico' => $inv?->stock_fisico ?? 0,
                    'disponible' => $inv ? $this->inventarioService->calcularDisponibilidad($inv) : 0,
                    'stock_minimo' => $inv?->stock_minimo ?? 5,
                    'stock_objetivo' => $inv?->stock_objetivo ?? 15,
                    'stock_maximo' => $inv?->stock_maximo ?? 30,
                ];
            });

        $productos = Producto::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo_modelo', 'nombre', 'categoria', 'precio_base']);

        $categorias = Producto::query()
            ->select('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');

        $ultimosIngresos = BitacoraMovimiento::with(['variante.producto', 'user'])
            ->where('sede_id', $sede->id)
            ->where('tipo_movimiento', 'ingreso')
            ->latest()
            ->limit(8)
            ->get();

        return view('inventario.ingreso', [
            'sedes' => $this->sedesPermitidas($request),
            'sedeActiva' => $sede,
            'tab' => $tab,
            'variantes' => $variantes,
            'productos' => $productos,
            'categorias' => $categorias,
            'ultimosIngresos' => $ultimosIngresos,
        ]);
    }

    /**
     * Procesa el ingreso de unidades para una mercadería existente en el catálogo.
     */
    public function ingresarExistente(IngresarMercaderiaExistenteRequest $request): RedirectResponse
    {
        $sede = $this->sedeDeTrabajo($request);

        try {
            $inventario = $this->inventarioService->ingresarMercaderiaExistente(
                sedeId: $sede->id,
                varianteId: (int) $request->validated('variante_id'),
                cantidad: (int) $request->validated('cantidad'),
                userId: $request->user()->id,
                motivo: $request->validated('motivo')
            );

            $this->alertaService->evaluarInventario($inventario);

            $cantidad = (int) $request->validated('cantidad');
            $sku = $inventario->variante->sku;

            return redirect()
                ->route('inventario.ingreso', ['sede_id' => $sede->id, 'tab' => 'existente'])
                ->with('success', "Se ingresaron +{$cantidad} unidades a {$sku}. Stock físico actual en sede: {$inventario->stock_fisico}.");
        } catch (\InvalidArgumentException $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Registra una nueva mercadería (nuevo SKU y opcionalmente nuevo modelo) y su stock inicial.
     */
    public function registrarNueva(RegistrarNuevaMercaderiaRequest $request): RedirectResponse
    {
        $sede = $this->sedeDeTrabajo($request);

        try {
            $inventario = $this->inventarioService->registrarNuevaMercaderia(
                sedeId: $sede->id,
                datos: $request->validated(),
                userId: $request->user()->id
            );

            // Evaluar alertas en todas las sedes para la nueva variante creada
            Inventario::where('variante_id', $inventario->variante_id)
                ->get()
                ->each(fn (Inventario $inv) => $this->alertaService->evaluarInventario($inv));

            $sku = $inventario->variante->sku;
            $nombre = $inventario->variante->producto?->nombre;

            return redirect()
                ->route('inventario.ingreso', ['sede_id' => $sede->id, 'tab' => 'nueva'])
                ->with('success', "Nueva mercadería {$sku} ({$nombre}) registrada con {$inventario->stock_fisico} unidades iniciales en {$sede->nombre}.");
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('inventario.ingreso', ['sede_id' => $sede->id, 'tab' => 'nueva'])
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Configuración de umbrales personalizados (mínimo / objetivo / máximo). Solo admin y gerente.
     */
    public function umbrales(Request $request): View
    {
        $sede = $this->sedeDeTrabajo($request);
        $busqueda = trim((string) $request->query('q', ''));

        $inventarios = Inventario::with('variante.producto')
            ->where('sede_id', $sede->id)
            ->when($busqueda !== '', fn ($q) => StockController::filtrarBusqueda($q, $busqueda))
            ->orderBy('variante_id')
            ->paginate(20)
            ->withQueryString();

        $inventarios->setCollection(
            $inventarios->getCollection()->map(fn ($inv) => $this->inventarioService->resumen($inv))
        );

        return view('inventario.umbrales', [
            'sedes' => $this->sedesPermitidas($request),
            'sedeActiva' => $sede,
            'inventarios' => $inventarios,
            'busqueda' => $busqueda,
        ]);
    }

    public function actualizarUmbral(ActualizarUmbralRequest $request, Inventario $inventario): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $inventario) {
                $this->inventarioService->actualizarUmbrales(
                    $inventario,
                    (int) $request->validated('stock_minimo'),
                    (int) $request->validated('stock_objetivo'),
                    (int) $request->validated('stock_maximo')
                );

                // Re-evaluar alertas inmediatamente tras cambiar umbrales
                $this->alertaService->evaluarInventario($inventario->refresh());
            });

            return back()->with('success', "Umbrales guardados para {$inventario->variante->sku}.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }
}
