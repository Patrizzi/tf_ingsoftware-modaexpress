<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventario extends Model
{
    use HasFactory;

    protected $table = 'inventarios';

    protected $fillable = [
        'sede_id',
        'variante_id',
        'stock_fisico',
        'stock_reservado',
        'stock_comprometido',
        'stock_minimo',
        'stock_objetivo',
        'stock_maximo',
    ];

    protected $casts = [
        'stock_fisico' => 'integer',
        'stock_reservado' => 'integer',
        'stock_comprometido' => 'integer',
        'stock_minimo' => 'integer',
        'stock_objetivo' => 'integer',
        'stock_maximo' => 'integer',
    ];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    /**
     * Disponibilidad real calculada en memoria o consulta.
     * Fórmula Core: Físico - Reservado - Comprometido
     */
    public function getDisponibilidadRealAttribute(): int
    {
        return max(0, (int)$this->stock_fisico - (int)$this->stock_reservado - (int)$this->stock_comprometido);
    }

    /**
     * Determina si el stock está en estado crítico (disponible <= mínimo).
     */
    public function isCritico(): bool
    {
        return $this->disponibilidad_real <= $this->stock_minimo;
    }

    /**
     * Determina si el stock está en estado preventivo (mínimo < disponible <= objetivo).
     */
    public function isPreventivo(): bool
    {
        return $this->disponibilidad_real > $this->stock_minimo && $this->disponibilidad_real <= $this->stock_objetivo;
    }

    /**
     * Determina si existe sobrestock / excedente (disponible > máximo).
     */
    public function hasSobrestock(): bool
    {
        return $this->disponibilidad_real > $this->stock_maximo;
    }
}
