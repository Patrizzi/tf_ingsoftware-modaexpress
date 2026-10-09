@extends('layouts.app')

@section('title', 'Stock')

@section('content')
@php
    $porReponer = $conteos['critico'] + $conteos['preventivo'];
    $chips = ['todos' => 'Todos', 'critico' => 'Crítico', 'preventivo' => 'Bajo', 'normal' => 'Normal', 'sobrestock' => 'Sobrestock'];
    $url = fn ($estado) => request()->fullUrlWithQuery(['estado' => $estado === 'todos' ? null : $estado]);
@endphp

<div class="page-head">
    <div>
        <h1>Stock de la tienda</h1>
        <p>Disponible = físico menos reservado y comprometido. Las prendas que necesitan reposición aparecen primero.</p>
    </div>
    <x-sede-select :sedes="$sedes" :sedeActiva="$sedeActiva" />
</div>

{{-- Foco de la pantalla: cuántas prendas hay que reponer ahora --}}
<section class="summary" aria-label="Resumen por estado">
    <a href="{{ $url('critico') }}" class="summary-item lead {{ $porReponer === 0 ? 'ok' : '' }}">
        <small>Prendas por reponer</small>
        <div class="big num">{{ $porReponer }}</div>
        <small>{{ $conteos['critico'] }} críticas y {{ $conteos['preventivo'] }} bajo el objetivo, de {{ $total }} en total</small>
    </a>
    <a href="{{ $url('critico') }}" class="summary-item"><x-estado estado="critico" /><div class="big num">{{ $conteos['critico'] }}</div></a>
    <a href="{{ $url('preventivo') }}" class="summary-item"><x-estado estado="preventivo" /><div class="big num">{{ $conteos['preventivo'] }}</div></a>
    <a href="{{ $url('sobrestock') }}" class="summary-item"><x-estado estado="sobrestock" /><div class="big num">{{ $conteos['sobrestock'] }}</div></a>
</section>

<section class="panel">
    <form method="GET" class="toolbar" role="search">
        <input type="hidden" name="sede_id" value="{{ $sedeActiva->id }}">
        @if ($filtro !== 'todos') <input type="hidden" name="estado" value="{{ $filtro }}"> @endif
        <div class="input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M21 21l-5.2-5.2M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
            <label for="q" class="sr-only">Buscar prenda</label>
            <input id="q" name="q" type="search" class="input" value="{{ $busqueda }}" placeholder="Buscar por nombre, color, SKU o código de barras">
        </div>
        <button class="btn">Buscar</button>
        @if ($busqueda !== '')
            <a class="btn btn-ghost" href="{{ request()->fullUrlWithQuery(['q' => null]) }}">Limpiar</a>
        @endif
    </form>

    <div class="toolbar">
        <nav class="chips" aria-label="Filtrar por estado">
            @foreach ($chips as $clave => $texto)
                <a href="{{ $url($clave) }}" class="chip" @if ($filtro === $clave) aria-current="true" @endif>
                    {{ $texto }} <span class="num">{{ $clave === 'todos' ? $total : $conteos[$clave] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    @if ($filas->isEmpty())
        <div class="empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" d="M21 21l-5.2-5.2M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
            @if ($total === 0)
                <h3>Esta tienda aún no tiene prendas registradas</h3>
                <p>El inventario se crea al registrar la mercadería de la sede. Pide al administrador que cargue el catálogo.</p>
            @elseif ($busqueda !== '')
                <h3>Ninguna prenda coincide con “{{ $busqueda }}”</h3>
                <p>Revisa la escritura o busca por el código de barras de la etiqueta.</p>
                <a class="btn" href="{{ request()->fullUrlWithQuery(['q' => null]) }}">Ver todas las prendas</a>
            @else
                <h3>No hay prendas en estado “{{ $chips[$filtro] }}”</h3>
                <p>Buena señal. Cambia el filtro para ver el resto del stock.</p>
                <a class="btn" href="{{ $url('todos') }}">Ver todas las prendas</a>
            @endif
        </div>
    @else
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                <tr>
                    <th scope="col">Prenda</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="r">Disponible</th>
                    <th scope="col" style="min-width: 200px">Frente a umbrales</th>
                    <th scope="col" class="r">Físico</th>
                    <th scope="col" class="r">Reservado</th>
                    <th scope="col" class="r">Comprometido</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($filas as $f)
                    <tr>
                        <td>
                            <div class="prod-name">{{ $f->producto }}</div>
                            <div class="prod-meta">Talla {{ $f->talla }} · {{ $f->color }} · <span class="num">{{ $f->sku }}</span></div>
                        </td>
                        <td><x-estado :estado="$f->estado" /></td>
                        <td class="r"><span class="disp-big">{{ $f->disponible }}</span></td>
                        <td data-col="gauge">
                            <x-medidor :disponible="$f->disponible" :minimo="$f->stock_minimo" :objetivo="$f->stock_objetivo" :maximo="$f->stock_maximo" :estado="$f->estado" leyenda />
                        </td>
                        <td class="r num hide-sm">{{ $f->stock_fisico }}</td>
                        <td class="r num hide-sm">{{ $f->stock_reservado }}</td>
                        <td class="r num hide-sm">{{ $f->stock_comprometido }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
