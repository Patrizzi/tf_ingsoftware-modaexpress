<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Services\AlertaService;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Stock en tiempo real de una sede: disponibilidad real frente a umbrales.
 */
class StockController extends Controller
{
    public function __construct(
        protected InventarioService $inventarioService,
        protected AlertaService $alertaService
    ) {}

    public function index(Request $request): View
    {
        $sede = $this->sedeDeTrabajo($request);
        $busqueda = trim((string) $request->query('q', ''));
        $filtro = (string) $request->query('estado', 'todos');

        $filas = Inventario::with('variante.producto')
            ->where('sede_id', $sede->id)
            ->when($busqueda !== '', fn ($q) => $this->filtrarBusqueda($q, $busqueda))
            ->get()
            ->map(fn ($inv) => $this->inventarioService->resumen($inv));

        // Conteos sobre el total de la sede (antes del filtro de estado) para los chips
        $conteos = collect(array_keys(InventarioService::PRIORIDAD))
            ->mapWithKeys(fn ($e) => [$e => $filas->where('estado', $e)->count()]);

        if (array_key_exists($filtro, InventarioService::PRIORIDAD)) {
            $filas = $filas->where('estado', $filtro);
        } else {
            $filtro = 'todos';
        }

        $filas = $filas->sortBy([
            fn ($a, $b) => InventarioService::PRIORIDAD[$a->estado] <=> InventarioService::PRIORIDAD[$b->estado],
            fn ($a, $b) => $a->disponible <=> $b->disponible,
        ])->values();

        return view('stock.index', [
            'sedes' => $this->sedesPermitidas($request),
            'sedeActiva' => $sede,
            'filas' => $filas,
            'conteos' => $conteos,
            'total' => $conteos->sum(),
            'filtro' => $filtro,
            'busqueda' => $busqueda,
        ]);
    }

    /** Búsqueda por SKU, código de barras o nombre de prenda (agrupada para no romper el where de sede). */
    public static function filtrarBusqueda($query, string $busqueda)
    {
        return $query->whereHas('variante', function ($v) use ($busqueda) {
            $v->where(function ($w) use ($busqueda) {
                $w->where('sku', 'like', "%{$busqueda}%")
                    ->orWhere('codigo_barra', 'like', "%{$busqueda}%")
                    ->orWhere('color', 'like', "%{$busqueda}%")
                    ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', "%{$busqueda}%"));
            });
        });
    }
}
