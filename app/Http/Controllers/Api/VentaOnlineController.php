<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarVentaRequest;
use App\Models\Sede;
use App\Services\VentaService;
use Illuminate\Http\JsonResponse;

class VentaOnlineController extends Controller
{
    public function __construct(
        protected VentaService $ventaService
    ) {}

    /**
     * Pedido confirmado en la tienda online. Descuenta stock de la sede online en la misma transacción,
     * así el inventario visible del canal web queda sincronizado al instante.
     */
    public function store(RegistrarVentaRequest $request): JsonResponse
    {
        $sede = Sede::find((int) $request->validated('sede_id'));

        if (! $sede || $sede->tipo !== 'online' || ! $sede->activa) {
            return response()->json([
                'success' => false,
                'mensaje' => 'La sede indicada no es un canal online activo.',
            ], 422);
        }

        try {
            $venta = $this->ventaService->confirmarVenta(
                datosVenta: [
                    'sede_id' => $sede->id,
                    'user_id' => null,
                    'canal' => 'online',
                    'metodo_pago' => (string) $request->validated('metodo_pago'),
                ],
                items: $request->validated('items')
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'mensaje' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'comprobante' => $venta->codigo_comprobante,
                'canal' => $venta->canal,
                'total' => (float) $venta->total,
                'estado' => $venta->estado,
                'lineas' => $venta->detalles->count(),
            ],
        ], 201);
    }
}
