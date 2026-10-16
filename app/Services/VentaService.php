<?php

namespace App\Services;

use App\Models\BitacoraMovimiento;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Variante;
use App\Models\Venta;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VentaService
{
    public function __construct(
        protected InventarioService $inventarioService,
        protected AlertaService $alertaService
    ) {}

    /** Tasa del IGV peruano. Los precios de etiqueta en retail ya lo incluyen. */
    public const TASA_IGV = 0.18;

    /**
     * Requerimiento Core 2: Confirmación Atómica de Ventas con DB::transaction().
     * Bloqueo pesimista, validación de disponibilidad, descuento físico y registro en bitácora.
     * En caso de cualquier falla o stock insuficiente, rollback automático completo.
     *
     * El precio se toma siempre del catálogo (nunca del cliente) y las líneas repetidas
     * de un mismo SKU se agrupan antes de validar el stock.
     *
     * @param array{
     *   sede_id: int,
     *   user_id: ?int,
     *   canal: string,
     *   metodo_pago: string,
     *   codigo_comprobante?: ?string
     * } $datosVenta
     * @param array<int, array{variante_id: int, cantidad: int}> $items
     * @return Venta
     * @throws Exception
     */
    public function confirmarVenta(array $datosVenta, array $items): Venta
    {
        if (empty($items)) {
            throw new Exception('No se puede procesar una venta sin artículos.');
        }

        // Agrupar cantidades por variante: dos líneas del mismo SKU deben validarse juntas
        $cantidades = [];
        foreach ($items as $item) {
            $varianteId = (int) $item['variante_id'];
            $cantidad = (int) $item['cantidad'];

            if ($cantidad <= 0) {
                throw new Exception("La cantidad debe ser mayor a 0 para el artículo ID {$varianteId}.");
            }

            $cantidades[$varianteId] = ($cantidades[$varianteId] ?? 0) + $cantidad;
        }

        // Bloquear siempre en el mismo orden evita interbloqueos entre cajas concurrentes
        ksort($cantidades);

        return DB::transaction(function () use ($datosVenta, $cantidades) {
            $total = 0.0;
            $itemsValidados = [];

            // 1. Verificación y Bloqueo Pesimista de Inventario de cada ítem
            foreach ($cantidades as $varianteId => $cantidadSolicitada) {
                $inventario = Inventario::with('variante')
                    ->where('sede_id', $datosVenta['sede_id'])
                    ->where('variante_id', $varianteId)
                    ->lockForUpdate()
                    ->first();

                if (! $inventario) {
                    $sku = Variante::find($varianteId)?->sku ?? "ID {$varianteId}";
                    throw new Exception("El producto {$sku} no está registrado en el inventario de esta sede.");
                }

                if (! $inventario->variante->activo) {
                    throw new Exception("El producto {$inventario->variante->sku} está desactivado y no se puede vender.");
                }

                // Cálculo estricto de disponibilidad: Físico - Reservado - Comprometido
                $disponible = $this->inventarioService->calcularDisponibilidad($inventario);

                if ($disponible < $cantidadSolicitada) {
                    throw new Exception("Stock insuficiente para {$inventario->variante->sku}: pediste {$cantidadSolicitada} y hay {$disponible} disponibles.");
                }

                $precioUnitario = (float) $inventario->variante->precio;
                $subtotalLinea = round($cantidadSolicitada * $precioUnitario, 2);
                $total += $subtotalLinea;

                $itemsValidados[] = [
                    'inventario' => $inventario,
                    'variante_id' => $varianteId,
                    'cantidad' => $cantidadSolicitada,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $subtotalLinea,
                ];
            }

            // 2. Creación del Comprobante de Venta.
            // El precio de etiqueta incluye IGV: base = total / 1.18, IGV = total - base.
            $total = round($total, 2);
            $baseImponible = round($total / (1 + self::TASA_IGV), 2);
            $impuesto = round($total - $baseImponible, 2);

            $canal = $datosVenta['canal'] ?? 'fisico';
            $tipoComprobante = $datosVenta['tipo_comprobante'] ?? 'boleta';
            $codigoComprobante = $datosVenta['codigo_comprobante'] ?? $this->generarCodigoComprobante($canal, (int) $datosVenta['sede_id'], $tipoComprobante);

            $venta = Venta::create([
                'codigo_comprobante' => $codigoComprobante,
                'tipo_comprobante' => $tipoComprobante,
                'sede_id' => $datosVenta['sede_id'],
                'user_id' => $datosVenta['user_id'] ?? null,
                'canal' => $canal,
                'estado' => 'completada',
                'subtotal' => $baseImponible,
                'impuesto' => $impuesto,
                'total' => $total,
                'metodo_pago' => $datosVenta['metodo_pago'] ?? 'efectivo',
                'cliente_documento' => $datosVenta['cliente_documento'] ?? null,
                'cliente_nombre' => $datosVenta['cliente_nombre'] ?? null,
                'cliente_direccion' => $datosVenta['cliente_direccion'] ?? null,
            ]);

            // 3. Descuento físico, Detalle y Bitácora de Auditoría por cada artículo
            foreach ($itemsValidados as $val) {
                /** @var Inventario $inventario */
                $inventario = $val['inventario'];
                $cantidad = $val['cantidad'];
                $stockAnterior = $inventario->stock_fisico;
                $stockNuevo = $stockAnterior - $cantidad;

                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'variante_id' => $val['variante_id'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $val['precio_unitario'],
                    'subtotal' => $val['subtotal'],
                ]);

                $inventario->update([
                    'stock_fisico' => $stockNuevo,
                ]);

                // Registrar en la Bitácora de Movimiento (auditoría obligatoria)
                BitacoraMovimiento::create([
                    'sede_id' => $inventario->sede_id,
                    'variante_id' => $inventario->variante_id,
                    'user_id' => $datosVenta['user_id'] ?? null,
                    'tipo_movimiento' => 'venta',
                    'cantidad' => -$cantidad,
                    'stock_fisico_anterior' => $stockAnterior,
                    'stock_fisico_nuevo' => $stockNuevo,
                    'referencia_tipo' => Venta::class,
                    'referencia_id' => $venta->id,
                    'motivo' => "Venta canal {$venta->canal}, comprobante {$venta->codigo_comprobante}",
                ]);

                // 4. Evaluar en tiempo real si el nuevo stock disparó alerta (crítica o preventiva)
                $inventario->refresh();
                $this->alertaService->evaluarInventario($inventario);
            }

            return $venta->load(['detalles.variante.producto', 'sede', 'user']);
        });
    }

    /**
     * Código legible y único: B (boleta) / F (factura) o W (web) + sede + fecha/hora + sufijo aleatorio.
     * Ej: B01-261008-153012-K7QD o F01-261008-153012-K7QD
     */
    protected function generarCodigoComprobante(string $canal, int $sedeId, string $tipoComprobante = 'boleta'): string
    {
        if ($canal === 'online') {
            $prefijo = 'W';
        } else {
            $prefijo = $tipoComprobante === 'factura' ? 'F' : 'B';
        }

        return sprintf('%s%02d-%s-%s', $prefijo, $sedeId, now()->format('ymd-His'), strtoupper(Str::random(4)));
    }

    /**
     * Búsqueda por código de barras o SKU para lectores ópticos en el frontend.
     */
    public function buscarPorCodigoBarraOSku(string $codigo, int $sedeId): ?array
    {
        $codigoLimpio = trim($codigo);

        $variante = Variante::with('producto')
            ->where(function ($q) use ($codigoLimpio) {
                $q->where('codigo_barra', $codigoLimpio)
                  ->orWhere('sku', $codigoLimpio);
            })
            ->where('activo', true)
            ->first();

        if (!$variante) {
            return null;
        }

        $inventario = Inventario::where('sede_id', $sedeId)
            ->where('variante_id', $variante->id)
            ->first();

        return [
            'id' => $variante->id,
            'sku' => $variante->sku,
            'codigo_barra' => $variante->codigo_barra,
            'nombre' => $variante->nombre_completo,
            'producto' => $variante->producto?->nombre,
            'talla' => $variante->talla,
            'color' => $variante->color,
            'precio' => (float) $variante->precio,
            'registrado_en_sede' => (bool) $inventario,
            'stock_fisico' => $inventario?->stock_fisico ?? 0,
            'disponible_real' => $inventario ? $this->inventarioService->calcularDisponibilidad($inventario) : 0,
            'stock_minimo' => $inventario?->stock_minimo,
            'stock_objetivo' => $inventario?->stock_objetivo,
            'stock_maximo' => $inventario?->stock_maximo,
            'estado' => $inventario ? $this->inventarioService->estado($inventario) : null,
        ];
    }
}
