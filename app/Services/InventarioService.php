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

    /**
     * Ingresa stock a una mercadería (variante) ya existente en el catálogo para una sede.
     * Si la variante aún no tenía fila de inventario en esa sede, la inicializa con umbrales base.
     */
    public function ingresarMercaderiaExistente(
        int $sedeId,
        int $varianteId,
        int $cantidad,
        ?int $userId = null,
        ?string $motivo = null
    ): Inventario {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad a ingresar debe ser mayor a 0.');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($sedeId, $varianteId, $cantidad, $userId, $motivo) {
            $variante = Variante::with('producto')->findOrFail($varianteId);

            $inventario = Inventario::where('sede_id', $sedeId)
                ->where('variante_id', $variante->id)
                ->lockForUpdate()
                ->first();

            if (! $inventario) {
                $inventario = Inventario::create([
                    'sede_id' => $sedeId,
                    'variante_id' => $variante->id,
                    'stock_fisico' => 0,
                    'stock_reservado' => 0,
                    'stock_comprometido' => 0,
                    'stock_minimo' => 5,
                    'stock_objetivo' => 15,
                    'stock_maximo' => 30,
                ]);
            }

            $this->registrarMovimiento(
                inventario: $inventario,
                tipoMovimiento: 'ingreso',
                cantidad: $cantidad,
                referenciaTipo: Variante::class,
                referenciaId: $variante->id,
                userId: $userId,
                motivo: $motivo ?: "Ingreso de mercadería existente ({$variante->sku})"
            );

            return $inventario->refresh()->load('variante.producto');
        });
    }

    /**
     * Registra una nueva mercadería (nueva variante con SKU propio, asociándola a un producto
     * existente o creando un nuevo modelo de prenda) y carga su stock inicial en la sede activa.
     * Además inicializa el registro de inventario en las demás sedes activas con stock 0.
     *
     * @param array{
     *   modo_producto: string,
     *   producto_id?: ?int,
     *   codigo_modelo?: ?string,
     *   nombre_producto?: ?string,
     *   categoria?: ?string,
     *   descripcion?: ?string,
     *   sku: string,
     *   codigo_barra: string,
     *   talla: string,
     *   color: string,
     *   precio: float|string,
     *   cantidad: int,
     *   stock_minimo: int,
     *   stock_objetivo: int,
     *   stock_maximo: int,
     *   motivo?: ?string
     * } $datos
     */
    public function registrarNuevaMercaderia(int $sedeId, array $datos, ?int $userId = null): Inventario
    {
        $minimo = (int) ($datos['stock_minimo'] ?? 5);
        $objetivo = (int) ($datos['stock_objetivo'] ?? 15);
        $maximo = (int) ($datos['stock_maximo'] ?? 30);
        $cantidad = (int) $datos['cantidad'];

        if ($cantidad < 0) {
            throw new InvalidArgumentException('La cantidad inicial no puede ser negativa.');
        }

        if ($minimo < 0 || $objetivo <= 0 || $maximo <= 0 || $minimo >= $objetivo || $objetivo > $maximo) {
            throw new InvalidArgumentException("Los umbrales deben cumplir: mínimo ({$minimo}) < objetivo ({$objetivo}) ≤ máximo ({$maximo}).");
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($sedeId, $datos, $userId, $minimo, $objetivo, $maximo, $cantidad) {
            // 1. Resolver o crear el Producto (modelo padre)
            if (($datos['modo_producto'] ?? 'nuevo') === 'existente' && ! empty($datos['producto_id'])) {
                $producto = \App\Models\Producto::findOrFail((int) $datos['producto_id']);
            } else {
                $producto = \App\Models\Producto::create([
                    'codigo_modelo' => strtoupper(trim((string) $datos['codigo_modelo'])),
                    'nombre' => trim((string) $datos['nombre_producto']),
                    'categoria' => trim((string) $datos['categoria']),
                    'descripcion' => ! empty($datos['descripcion']) ? trim((string) $datos['descripcion']) : null,
                    'precio_base' => (float) $datos['precio'],
                    'activo' => true,
                ]);
            }

            // 2. Crear la nueva Variante con SKU y código de barras únicos
            $variante = Variante::create([
                'producto_id' => $producto->id,
                'sku' => strtoupper(trim((string) $datos['sku'])),
                'codigo_barra' => trim((string) $datos['codigo_barra']),
                'talla' => strtoupper(trim((string) $datos['talla'])),
                'color' => trim((string) $datos['color']),
                'precio' => (float) $datos['precio'],
                'activo' => true,
            ]);

            // 3. Crear el inventario en la sede actual con stock 0 primero y asentar el ingreso en bitácora
            $inventarioSede = Inventario::create([
                'sede_id' => $sedeId,
                'variante_id' => $variante->id,
                'stock_fisico' => 0,
                'stock_reservado' => 0,
                'stock_comprometido' => 0,
                'stock_minimo' => $minimo,
                'stock_objetivo' => $objetivo,
                'stock_maximo' => $maximo,
            ]);

            if ($cantidad > 0) {
                $this->registrarMovimiento(
                    inventario: $inventarioSede,
                    tipoMovimiento: 'ingreso',
                    cantidad: $cantidad,
                    referenciaTipo: Variante::class,
                    referenciaId: $variante->id,
                    userId: $userId,
                    motivo: ! empty($datos['motivo'])
                        ? trim((string) $datos['motivo'])
                        : "Alta e ingreso inicial de nueva mercadería ({$variante->sku})"
                );
            }

            // 4. Inicializar en las demás sedes activas con stock 0 para mantener el catálogo sincronizado
            $otrasSedes = \App\Models\Sede::where('id', '!=', $sedeId)->pluck('id');
            foreach ($otrasSedes as $otraSedeId) {
                Inventario::firstOrCreate(
                    [
                        'sede_id' => $otraSedeId,
                        'variante_id' => $variante->id,
                    ],
                    [
                        'stock_fisico' => 0,
                        'stock_reservado' => 0,
                        'stock_comprometido' => 0,
                        'stock_minimo' => $minimo,
                        'stock_objetivo' => $objetivo,
                        'stock_maximo' => $maximo,
                    ]
                );
            }

            return $inventarioSede->refresh()->load('variante.producto');
        });
    }
}
