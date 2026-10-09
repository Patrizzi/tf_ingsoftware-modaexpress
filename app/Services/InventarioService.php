<?php

namespace App\Services;

use App\Models\Inventario;
use App\Models\Variante;
use App\Models\BitacoraMovimiento;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Collection;

class InventarioService
{
    public const CRITICO = 'critico';
    public const PREVENTIVO = 'preventivo';
    public const NORMAL = 'normal';
    public const SOBRESTOCK = 'sobrestock';

    /** Orden de prioridad para listar: lo que requiere acción primero. */
    public const PRIORIDAD = [
        self::CRITICO => 0,
        self::PREVENTIVO => 1,
        self::SOBRESTOCK => 2,
        self::NORMAL => 3,
    ];

    /**
     * Requerimiento Core 1: Cálculo de Disponibilidad Real.
     * Fórmula: Físico - Reservado - Comprometido
     */
    public function calcularDisponibilidad(Inventario $inventario): int
    {
        $disponible = (int)$inventario->stock_fisico 
                    - (int)$inventario->stock_reservado 
                    - (int)$inventario->stock_comprometido;

        return max(0, $disponible);
    }

    /**
     * Clasifica el inventario según sus umbrales personalizados:
     * crítico (disp <= mínimo), preventivo (disp <= objetivo), sobrestock (disp > máximo) o normal.
     */
    public function estado(Inventario $inventario): string
    {
        $disponible = $this->calcularDisponibilidad($inventario);

        return match (true) {
            $disponible <= $inventario->stock_minimo => self::CRITICO,
            $disponible <= $inventario->stock_objetivo => self::PREVENTIVO,
            $disponible > $inventario->stock_maximo => self::SOBRESTOCK,
            default => self::NORMAL,
        };
    }

    /**
     * Fila lista para las vistas: datos de la prenda, componentes del stock, umbrales y estado.
     * Requiere el inventario con variante.producto cargado.
     */
    public function resumen(Inventario $inventario): object
    {
        $variante = $inventario->variante;

        return (object) [
            'inventario_id' => $inventario->id,
            'variante_id' => $inventario->variante_id,
            'sku' => $variante->sku,
            'codigo_barra' => $variante->codigo_barra,
            'producto' => $variante->producto?->nombre ?? 'Prenda',
            'categoria' => $variante->producto?->categoria,
            'talla' => $variante->talla,
            'color' => $variante->color,
            'precio' => (float) $variante->precio,
            'stock_fisico' => $inventario->stock_fisico,
            'stock_reservado' => $inventario->stock_reservado,
            'stock_comprometido' => $inventario->stock_comprometido,
            'disponible' => $this->calcularDisponibilidad($inventario),
            'stock_minimo' => $inventario->stock_minimo,
            'stock_objetivo' => $inventario->stock_objetivo,
            'stock_maximo' => $inventario->stock_maximo,
            'estado' => $this->estado($inventario),
            'actualizado' => $inventario->updated_at,
        ];
    }

    /**
     * Obtiene el inventario con bloqueo pesimista para operaciones atómicas.
     */
    public function obtenerParaBloqueo(int $sedeId, int $varianteId): ?Inventario
    {
        return Inventario::where('sede_id', $sedeId)
            ->where('variante_id', $varianteId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Valida y actualiza los umbrales de stock asegurando la regla:
     * stock_minimo < stock_objetivo <= stock_maximo
     */
    public function actualizarUmbrales(Inventario $inventario, int $minimo, int $objetivo, int $maximo): Inventario
    {
        if ($minimo < 0 || $objetivo <= 0 || $maximo <= 0) {
            throw new InvalidArgumentException('Los umbrales deben ser valores enteros positivos.');
        }

        if ($minimo >= $objetivo) {
            throw new InvalidArgumentException("El umbral mínimo ({$minimo}) debe ser estrictamente menor al stock objetivo ({$objetivo}).");
        }

        if ($objetivo > $maximo) {
            throw new InvalidArgumentException("El stock objetivo ({$objetivo}) no puede superar el stock máximo ({$maximo}).");
        }

        $inventario->update([
            'stock_minimo' => $minimo,
            'stock_objetivo' => $objetivo,
            'stock_maximo' => $maximo,
        ]);

        return $inventario;
    }

    /**
     * Listado optimizado con Eager Loading para evitar N+1 y garantizar respuesta < 2s.
     */
    public function listarPorSede(int $sedeId, ?string $busqueda = null)
    {
        $query = Inventario::with(['variante.producto', 'sede'])
            ->where('sede_id', $sedeId);

        if ($busqueda) {
            $query->whereHas('variante', function ($q) use ($busqueda) {
                $q->where('sku', 'like', "%{$busqueda}%")
                  ->orWhere('codigo_barra', 'like', "%{$busqueda}%")
                  ->orWhereHas('producto', function ($pq) use ($busqueda) {
                      $pq->where('nombre', 'like', "%{$busqueda}%")
                         ->orWhere('codigo_modelo', 'like', "%{$busqueda}%");
                  });
            });
        }

        return $query->paginate(20);
    }

    /**
     * Registra un movimiento en la bitácora y actualiza el stock físico de forma atómica.
     */
    public function registrarMovimiento(
        Inventario $inventario,
        string $tipoMovimiento,
        int $cantidad,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
        ?int $userId = null,
        ?string $motivo = null
    ): BitacoraMovimiento {
        $stockAnterior = $inventario->stock_fisico;
        $stockNuevo = max(0, $stockAnterior + $cantidad);

        $inventario->update([
            'stock_fisico' => $stockNuevo,
        ]);

        return BitacoraMovimiento::create([
            'sede_id' => $inventario->sede_id,
            'variante_id' => $inventario->variante_id,
            'user_id' => $userId,
            'tipo_movimiento' => $tipoMovimiento,
            'cantidad' => $cantidad,
            'stock_fisico_anterior' => $stockAnterior,
            'stock_fisico_nuevo' => $stockNuevo,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            'motivo' => $motivo,
        ]);
    }
}
