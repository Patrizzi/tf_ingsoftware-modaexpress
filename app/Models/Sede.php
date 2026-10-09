<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    use HasFactory;

    protected $table = 'sedes';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'direccion',
        'telefono',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(BitacoraMovimiento::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public function scopeFisicas($query)
    {
        return $query->where('tipo', 'fisica');
    }

    public function scopeOnline($query)
    {
        return $query->where('tipo', 'online');
    }
}
