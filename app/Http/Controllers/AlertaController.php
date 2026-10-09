<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Services\AlertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Módulo de alertas: alertas activas generadas por los umbrales y sugerencias de transferencia.
 */
class AlertaController extends Controller
{
    public function __construct(
        protected AlertaService $alertaService
    ) {}

    public function index(Request $request): View
    {
        $sede = $this->sedeDeTrabajo($request);
        $tipo = (string) $request->query('tipo', 'todas');

        // Re-evaluar la sede garantiza que las alertas reflejen el stock de este momento
        $this->alertaService->evaluarSede($sede->id);

        $activas = Alerta::with('variante.producto')
            ->where('sede_id', $sede->id)
            ->activas()
            ->get();

        $conteos = [
            'critico' => $activas->where('tipo', 'critico')->count(),
            'preventivo' => $activas->where('tipo', 'preventivo')->count(),
            'sobrestock' => $activas->where('tipo', 'sobrestock')->count(),
        ];

        if (array_key_exists($tipo, $conteos)) {
            $activas = $activas->where('tipo', $tipo);
        } else {
            $tipo = 'todas';
        }

        $orden = ['critico' => 0, 'preventivo' => 1, 'sobrestock' => 2];
        $activas = $activas->sortBy([
            fn ($a, $b) => $orden[$a->tipo] <=> $orden[$b->tipo],
            fn ($a, $b) => $a->stock_disponible_calculado <=> $b->stock_disponible_calculado,
        ])->values();

        $resueltas = Alerta::with('variante.producto')
            ->where('sede_id', $sede->id)
            ->where('estado', 'resuelta')
            ->latest('fecha_resuelta')
            ->limit(8)
            ->get();

        return view('alertas.index', [
            'sedes' => $this->sedesPermitidas($request),
            'sedeActiva' => $sede,
            'alertas' => $activas,
            'resueltas' => $resueltas,
            'conteos' => $conteos,
            'tipo' => $tipo,
        ]);
    }

    /**
     * JSON consultado por el panel: tiendas con excedente de la prenda que falta en esta sede.
     */
    public function sugerenciasTransferencia(Request $request, int $varianteId): JsonResponse
    {
        $sede = $this->sedeDeTrabajo($request);

        $resultado = $this->alertaService->obtenerSugerenciasTransferencia($sede->id, $varianteId);

        return response()->json($resultado, $resultado['success'] ? 200 : 404);
    }
}
