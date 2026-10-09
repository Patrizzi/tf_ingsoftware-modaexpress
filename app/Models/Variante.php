<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Variante extends Model
{
    use HasFactory;

    protected $table = 'variantes';

    protected $fillable = [
        'producto_id',
        'sku',
        'codigo_barra',
        'talla',
        'color',
        'precio',
        'activo',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class);
    }

    public function inventarioPorSede(int $sedeId): HasOne
    {
        return $this->hasOne(Inventario::class)->where('sede_id', $sedeId);
    }

    public function detalleVentas(): HasMany
    {
        return $this->hasMany(DetalleVenta::class);
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(BitacoraMovimiento::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }

    /**
     * Descripción completa de la prenda para interfaces y tickets.
     */
    public function getNombreCompletoAttribute(): string
    {
        $nombreBase = $this->producto ? $this->producto->nombre : 'Prenda';
        return "{$nombreBase} - Talla {$this->talla} ({$this->color})";
    }
}
