<?php

namespace App\Services;

use App\Models\Alerta;
use App\Models\Inventario;
use App\Models\Sede;
use App\Models\Variante;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AlertaService
{
    public function __construct(
        protected InventarioService $inventarioService
    ) {}

    /**
     * Evalúa el inventario de una variante en una sede específica y genera/actualiza alertas.
     * Reglas:
     * - Crítico: disponibilidad <= stock_minimo
     * - Preventivo: stock_minimo < disponibilidad <= stock_objetivo
     * - Normal/Sobrestock: Si supera objetivo y había alerta activa, se marca como resuelta.
     * Evita duplicación de alertas activas para la misma variante y sede.
     */
    public function evaluarInventario(Inventario $inventario): ?Alerta
    {
        // 1. Validar integridad de umbrales
        if ($inventario->stock_minimo >= $inventario->stock_objetivo || $inventario->stock_objetivo > $inventario->stock_maximo) {
            throw new InvalidArgumentException('Inconsistencia en umbrales: se requiere minimo < objetivo <= maximo.');
        }

        // 2. Cálculo de disponibilidad real
        $disponible = $this->inventarioService->calcularDisponibilidad($inventario);
        $min = $inventario->stock_minimo;
        $obj = $inventario->stock_objetivo;
        $max = $inventario->stock_maximo;

        // 3. Determinar el estado requerido (misma regla que usa todo el sistema)
        $estado = $this->inventarioService->estado($inventario);
        $tipoAlerta = $estado === InventarioService::NORMAL ? null : $estado;

        $mensaje = match ($estado) {
            InventarioService::CRITICO => "Quedan {$disponible} unidades disponibles y el mínimo es {$min}. Reponer con urgencia.",
            InventarioService::PREVENTIVO => "Hay {$disponible} unidades disponibles, por debajo del objetivo de {$obj}. Conviene reponer.",
            InventarioService::SOBRESTOCK => "Hay {$disponible} unidades disponibles, más que el máximo de {$max}. Se puede transferir a otra tienda.",
            default => null,
        };

        // 4. Buscar alerta activa existente para evitar duplicados
        $alertaActiva = Alerta::where('sede_id', $inventario->sede_id)
            ->where('variante_id', $inventario->variante_id)
            ->where('estado', 'activa')
            ->first();

        // 5. Gestión del ciclo de vida de la alerta
        if ($tipoAlerta !== null) {
            if ($alertaActiva) {
                // Actualizar snapshot de la alerta activa existente
                $alertaActiva->update([
                    'tipo' => $tipoAlerta,
                    'stock_disponible_calculado' => $disponible,
                    'stock_minimo_umbral' => $min,
                    'stock_objetivo_umbral' => $obj,
                    'stock_maximo_umbral' => $max,
                    'mensaje' => $mensaje,
                ]);
                return $alertaActiva;
            }

            // Crear nueva alerta única
            return Alerta::create([
                'sede_id' => $inventario->sede_id,
                'variante_id' => $inventario->variante_id,
                'tipo' => $tipoAlerta,
                'estado' => 'activa',
                'stock_disponible_calculado' => $disponible,
                'stock_minimo_umbral' => $min,
                'stock_objetivo_umbral' => $obj,
                'stock_maximo_umbral' => $max,
                'mensaje' => $mensaje,
            ]);
        }

        // Si el stock está en rango óptimo normal y había alerta activa, resolverla
        if ($alertaActiva) {
            $alertaActiva->update([
                'estado' => 'resuelta',
                'fecha_resuelta' => now(),
            ]);
        }

        return null;
    }

    /**
     * Evalúa masivamente el inventario de una sede.
     */
    public function evaluarSede(int $sedeId): int
    {
        $inventarios = Inventario::where('sede_id', $sedeId)->get();
        $generadas = 0;

        foreach ($inventarios as $inv) {
            if ($this->evaluarInventario($inv)) {
                $generadas++;
            }
        }

        return $generadas;
    }

    /**
     * Obtiene sugerencias de transferencia entre sucursales cuando una tienda tiene déficit.
     * Busca otras sedes donde disponible > stock_maximo (excedente).
     */
    public function obtenerSugerenciasTransferencia(int $sedeSolicitanteId, int $varianteId): array
    {
        $inventarioLocal = Inventario::with(['variante.producto', 'sede'])
            ->where('sede_id', $sedeSolicitanteId)
            ->where('variante_id', $varianteId)
            ->first();

        if (!$inventarioLocal) {
            return [
                'success' => false,
                'mensaje' => 'No se encontró registro de inventario para esta prenda en la sede.',
                'sugerencias' => [],
            ];
        }

        $dispLocal = $this->inventarioService->calcularDisponibilidad($inventarioLocal);
        $deficit = max(0, $inventarioLocal->stock_objetivo - $dispLocal);

        // Buscar en las demás sedes activas que tengan excedente (disponible > stock_maximo)
        $inventariosOtrasSedes = Inventario::with('sede')
            ->where('variante_id', $varianteId)
            ->where('sede_id', '!=', $sedeSolicitanteId)
            ->whereHas('sede', fn($q) => $q->where('activa', true))
            ->get();

        $sedesConExcedente = [];

        foreach ($inventariosOtrasSedes as $invRemoto) {
            $dispRemoto = $this->inventarioService->calcularDisponibilidad($invRemoto);
            $maxRemoto = $invRemoto->stock_maximo;

            if ($dispRemoto > $maxRemoto) {
                $excedente = $dispRemoto - $maxRemoto;
                $sugerida = min($excedente, $deficit > 0 ? $deficit : $excedente);

                $sedesConExcedente[] = [
                    'sede_origen_id' => $invRemoto->sede_id,
                    'sede_nombre' => $invRemoto->sede->nombre,
                    'sede_tipo' => $invRemoto->sede->tipo,
                    'stock_fisico' => $invRemoto->stock_fisico,
                    'stock_disponible' => $dispRemoto,
                    'stock_maximo' => $maxRemoto,
                    'unidades_excedentes' => $excedente,
                    'cantidad_sugerida_transferir' => $sugerida,
                ];
            }
        }

        // Ordenar por mayor disponibilidad
        usort($sedesConExcedente, fn($a, $b) => $b['unidades_excedentes'] <=> $a['unidades_excedentes']);

        return [
            'success' => true,
            'variante' => [
                'id' => $inventarioLocal->variante_id,
                'sku' => $inventarioLocal->variante->sku,
                'codigo_barra' => $inventarioLocal->variante->codigo_barra,
                'nombre' => $inventarioLocal->variante->nombre_completo,
                'producto' => $inventarioLocal->variante->producto?->nombre,
                'talla' => $inventarioLocal->variante->talla,
                'color' => $inventarioLocal->variante->color,
                'sede_nombre' => $inventarioLocal->sede->nombre,
                'stock_local_disponible' => $dispLocal,
                'stock_local_minimo' => $inventarioLocal->stock_minimo,
                'stock_local_objetivo' => $inventarioLocal->stock_objetivo,
                'deficit_calculado' => $deficit,
            ],
            'tiene_excedente_externo' => count($sedesConExcedente) > 0,
            'sugerencias' => $sedesConExcedente,
        ];
    }
}
