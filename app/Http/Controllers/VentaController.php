<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarVentaRequest;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VentaController extends Controller
{
    public function __construct(
        protected VentaService $ventaService
    ) {}

    /**
     * Punto de venta de tienda física con lector de código de barras.
     */
    public function pos(Request $request): View
    {
        $sede = $this->sedeDeTrabajo($request, soloFisicas: true);

        $ultimas = Venta::where('sede_id', $sede->id)
            ->withCount('detalles')
            ->latest()
            ->limit(5)
            ->get();

        return view('ventas.pos', [
            'sedes' => $this->sedesPermitidas($request, soloFisicas: true),
            'sedeActiva' => $sede,
            'ultimas' => $ultimas,
        ]);
    }

    public function buscarCodigo(Request $request): JsonResponse
    {
        $sede = $this->sedeDeTrabajo($request, soloFisicas: true);
        $codigo = trim((string) $request->query('codigo', ''));

        if ($codigo === '') {
            return response()->json(['success' => false, 'mensaje' => 'Escanea o escribe un código.'], 422);
        }

        $producto = $this->ventaService->buscarPorCodigoBarraOSku($codigo, $sede->id);

        if (! $producto) {
            return response()->json([
                'success' => false,
                'mensaje' => "No hay ningún producto activo con el código {$codigo}.",
            ], 404);
        }

        return response()->json(['success' => true, 'data' => $producto]);
    }

    public function store(RegistrarVentaRequest $request): JsonResponse
    {
        $sede = $this->sedeDeTrabajo($request, soloFisicas: true);

        try {
            $venta = $this->ventaService->confirmarVenta(
                datosVenta: [
                    'sede_id' => $sede->id,
                    'user_id' => $request->user()->id,
                    'canal' => 'fisico',
                    'metodo_pago' => (string) $request->validated('metodo_pago'),
                ],
                items: $request->validated('items')
            );
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'mensaje' => "Venta {$venta->codigo_comprobante} registrada. El stock ya se actualizó.",
            'venta' => [
                'codigo' => $venta->codigo_comprobante,
                'subtotal' => (float) $venta->subtotal,
                'impuesto' => (float) $venta->impuesto,
                'total' => (float) $venta->total,
                'unidades' => (int) $venta->detalles->sum('cantidad'),
            ],
        ], 201);
    }
}
