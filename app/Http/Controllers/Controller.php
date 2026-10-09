<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Sedes que el usuario puede consultar.
     * El administrador ve todas las sedes activas; gerentes y vendedores solo la suya.
     */
    protected function sedesPermitidas(Request $request, bool $soloFisicas = false): Collection
    {
        $user = $request->user();

        $query = Sede::activas()->orderBy('codigo');

        if ($soloFisicas) {
            $query->fisicas();
        }

        if (! $user->isAdmin()) {
            $query->whereKey($user->sede_id);
        }

        return $query->get();
    }

    /**
     * Resuelve la sede de trabajo a partir de ?sede_id=, validando que el usuario tenga acceso.
     * Si el parámetro no se envía, usa la sede del usuario o la primera permitida.
     */
    protected function sedeDeTrabajo(Request $request, bool $soloFisicas = false): Sede
    {
        $permitidas = $this->sedesPermitidas($request, $soloFisicas);

        abort_if($permitidas->isEmpty(), 403, 'Tu usuario no tiene una sede asignada.');

        $solicitada = $request->input('sede_id');

        if ($solicitada !== null) {
            $sede = $permitidas->firstWhere('id', (int) $solicitada);
            abort_if(! $sede, 403, 'No tienes acceso a esa sede.');

            return $sede;
        }

        return $permitidas->firstWhere('id', $request->user()->sede_id) ?? $permitidas->first();
    }
}
