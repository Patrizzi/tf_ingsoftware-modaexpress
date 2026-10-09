<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarUmbralRequest;
use App\Models\Inventario;
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
