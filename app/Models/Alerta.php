<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alerta extends Model
{
    use HasFactory;

    protected $table = 'alertas';

    protected $fillable = [
        'sede_id',
        'variante_id',
        'tipo',
        'estado',
        'stock_disponible_calculado',
        'stock_minimo_umbral',
        'stock_objetivo_umbral',
        'stock_maximo_umbral',
        'mensaje',
        'fecha_resuelta',
    ];

    protected $casts = [
        'stock_disponible_calculado' => 'integer',
        'stock_minimo_umbral' => 'integer',
        'stock_objetivo_umbral' => 'integer',
        'stock_maximo_umbral' => 'integer',
        'fecha_resuelta' => 'datetime',
    ];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', 'activa');
    }

    public function scopeCriticas($query)
    {
        return $query->where('tipo', 'critico');
    }

    public function scopePreventivas($query)
    {
        return $query->where('tipo', 'preventivo');
    }
}
