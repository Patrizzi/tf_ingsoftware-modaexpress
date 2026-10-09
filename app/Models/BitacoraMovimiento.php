<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BitacoraMovimiento extends Model
{
    use HasFactory;

    protected $table = 'bitacora_movimientos';

    protected $fillable = [
        'sede_id',
        'variante_id',
        'user_id',
        'tipo_movimiento',
        'cantidad',
        'stock_fisico_anterior',
        'stock_fisico_nuevo',
        'referencia_tipo',
        'referencia_id',
        'motivo',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'stock_fisico_anterior' => 'integer',
        'stock_fisico_nuevo' => 'integer',
    ];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referencia(): MorphTo
    {
        return $this->morphTo();
    }
}
