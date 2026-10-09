<?php

namespace App\Http\Controllers;

use App\Models\BitacoraMovimiento;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bitácora de movimientos: cada cambio de stock físico con su responsable y referencia.
 */
class MovimientoController extends Controller
{
    public const TIPOS = [
        'venta' => 'Venta',
        'ingreso' => 'Ingreso',
        'ajuste' => 'Ajuste',
        'transferencia_salida' => 'Transferencia (salida)',
        'transferencia_entrada' => 'Transferencia (entrada)',
        'devolucion' => 'Devolución',
    ];

    public function index(Request $request): View
    {
        $sede = $this->sedeDeTrabajo($request);
        $tipo = (string) $request->query('tipo', '');

        $movimientos = BitacoraMovimiento::with(['variante.producto', 'user'])
            ->where('sede_id', $sede->id)
            ->when(array_key_exists($tipo, self::TIPOS), fn ($q) => $q->where('tipo_movimiento', $tipo))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('movimientos.index', [
            'sedes' => $this->sedesPermitidas($request),
            'sedeActiva' => $sede,
            'movimientos' => $movimientos,
            'tipos' => self::TIPOS,
            'tipo' => $tipo,
        ]);
    }
}
