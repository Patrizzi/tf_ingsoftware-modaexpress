@extends('layouts.app')

@section('title', 'Movimientos')

@section('content')
<div class="page-head">
    <div>
        <h1>Bitácora de movimientos de inventario</h1>
        <p>Registro de auditoría inmutable de cada cambio en el stock físico: ventas, ingresos, ajustes y transferencias.</p>
    </div>
    <x-sede-select :sedes="$sedes" :sedeActiva="$sedeActiva" />
</div>

<section class="panel">
    <div class="toolbar">
        <nav class="chips" aria-label="Filtrar movimientos">
            <a href="{{ request()->fullUrlWithQuery(['tipo' => null]) }}" class="chip" @if ($tipo === '') aria-current="true" @endif>
                Todos
            </a>
            @foreach ($tipos as $clave => $nombre)
                <a href="{{ request()->fullUrlWithQuery(['tipo' => $clave]) }}" class="chip" @if ($tipo === $clave) aria-current="true" @endif>
                    {{ $nombre }}
                </a>
            @endforeach
        </nav>
    </div>

    @if ($movimientos->isEmpty())
        <div class="empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            @if ($tipo !== '')
                <h3>No hay movimientos del tipo “{{ $tipos[$tipo] ?? $tipo }}”</h3>
                <p>No se encontraron registros bajo este filtro para la tienda seleccionada.</p>
                <a class="btn" href="{{ request()->fullUrlWithQuery(['tipo' => null]) }}">Ver todos los movimientos</a>
            @else
                <h3>Aún no hay movimientos registrados en esta tienda</h3>
                <p>Los movimientos se asientan automáticamente con cada venta, ingreso o ajuste en el inventario.</p>
            @endif
        </div>
    @else
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                    <tr>
                        <th scope="col">Fecha y hora</th>
                        <th scope="col">Prenda / SKU</th>
                        <th scope="col">Tipo de movimiento</th>
                        <th scope="col" class="r">Variación</th>
                        <th scope="col" class="r">Stock anterior</th>
                        <th scope="col" class="r">Stock nuevo</th>
                        <th scope="col">Usuario / Referencia</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($movimientos as $m)
                        <tr>
                            <td class="muted num" style="white-space: nowrap">
                                <div>{{ $m->created_at->format('d/m/Y') }}</div>
                                <div style="font-size: .8125rem">{{ $m->created_at->format('H:i:s') }}</div>
                            </td>
                            <td>
                                <div class="prod-name">{{ $m->variante?->producto?->nombre ?? 'Prenda eliminada' }}</div>
                                <div class="prod-meta">
                                    Talla {{ $m->variante?->talla ?? '-' }} · {{ $m->variante?->color ?? '-' }} · <span class="num">{{ $m->variante?->sku ?? '-' }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="chip" style="min-height: 28px; padding: 0 8px; font-size: .8125rem">
                                    {{ $tipos[$m->tipo_movimiento] ?? ucfirst($m->tipo_movimiento) }}
                                </span>
                            </td>
                            <td class="r num" style="font-weight: 600">
                                @if ($m->cantidad > 0)
                                    <span style="color: var(--normal-text)">+{{ $m->cantidad }}</span>
                                @elseif ($m->cantidad < 0)
                                    <span style="color: var(--critico-text)">{{ $m->cantidad }}</span>
                                @else
                                    <span>0</span>
                                @endif
                            </td>
                            <td class="r num hide-sm">{{ $m->stock_fisico_anterior }}</td>
                            <td class="r num" style="font-weight: 600">{{ $m->stock_fisico_nuevo }}</td>
                            <td>
                                <div>{{ $m->user?->name ?? 'Sistema' }}</div>
                                @if ($m->motivo)
                                    <div class="muted" style="font-size: .8125rem">{{ $m->motivo }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($movimientos->hasPages())
            <nav class="pager" aria-label="Paginación de movimientos">
                <span class="muted">{{ $movimientos->firstItem() }}–{{ $movimientos->lastItem() }} de {{ $movimientos->total() }} movimientos</span>
                <span style="display:flex;gap:8px">
                    @if ($movimientos->onFirstPage())
                        <span class="btn btn-sm" aria-disabled="true" style="opacity:.5">Anterior</span>
                    @else
                        <a class="btn btn-sm" href="{{ $movimientos->previousPageUrl() }}">Anterior</a>
                    @endif
                    @if ($movimientos->hasMorePages())
                        <a class="btn btn-sm" href="{{ $movimientos->nextPageUrl() }}">Siguiente</a>
                    @else
                        <span class="btn btn-sm" aria-disabled="true" style="opacity:.5">Siguiente</span>
                    @endif
                </span>
            </nav>
        @endif
    @endif
</section>
@endsection
