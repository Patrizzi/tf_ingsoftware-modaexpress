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
                    'tipo_comprobante' => (string) ($request->validated('tipo_comprobante') ?? 'boleta'),
                    'cliente_documento' => $request->validated('cliente_documento'),
                    'cliente_nombre' => $request->validated('cliente_nombre'),
                    'cliente_direccion' => $request->validated('cliente_direccion'),
                ],
                items: $request->validated('items')
            );
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'mensaje' => "Venta {$venta->codigo_comprobante} registrada. El stock ya se actualizó.",
            'venta' => $this->serializarComprobante($venta),
        ], 201);
    }

    /**
     * Vista imprimible de Boleta o Factura Electrónica.
     */
    public function comprobante(Request $request, Venta $venta): View|JsonResponse
    {
        $permitidas = $this->sedesPermitidas($request);
        abort_if(! $permitidas->contains('id', $venta->sede_id), 403, 'No tienes acceso a las ventas de esta sede.');

        $venta->load(['detalles.variante.producto', 'sede', 'user']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'venta' => $this->serializarComprobante($venta),
            ]);
        }

        return view('ventas.comprobante', [
            'venta' => $venta,
        ]);
    }

    protected function serializarComprobante(Venta $venta): array
    {
        return [
            'id' => $venta->id,
            'codigo' => $venta->codigo_comprobante,
            'tipo_comprobante' => $venta->tipo_comprobante ?? 'boleta',
            'fecha' => $venta->created_at?->format('d/m/Y H:i:s'),
            'sede' => [
                'nombre' => $venta->sede?->nombre,
                'direccion' => $venta->sede?->direccion,
                'telefono' => $venta->sede?->telefono,
            ],
            'cajero' => $venta->user?->name ?? 'Caja',
            'cliente' => [
                'documento' => $venta->cliente_documento ?: '00000000',
                'nombre' => $venta->cliente_nombre ?: 'Cliente Varios',
                'direccion' => $venta->cliente_direccion,
            ],
            'metodo_pago' => $venta->metodo_pago,
            'subtotal' => (float) $venta->subtotal,
            'impuesto' => (float) $venta->impuesto,
            'total' => (float) $venta->total,
            'unidades' => (int) $venta->detalles->sum('cantidad'),
            'url_comprobante' => route('ventas.comprobante', $venta->id),
            'items' => $venta->detalles->map(fn ($d) => [
                'sku' => $d->variante?->sku,
                'nombre' => $d->variante?->producto?->nombre ?? 'Prenda',
                'talla' => $d->variante?->talla,
                'color' => $d->variante?->color,
                'cantidad' => (int) $d->cantidad,
                'precio_unitario' => (float) $d->precio_unitario,
                'subtotal' => (float) $d->subtotal,
            ])->values()->all(),
        ];
    }
}
