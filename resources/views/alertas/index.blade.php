@extends('layouts.app')

@section('title', 'Alertas')

@section('content')
@php
    $chips = ['todas' => 'Todas', 'critico' => 'Crítico', 'preventivo' => 'Bajo', 'sobrestock' => 'Sobrestock'];
    $totalActivas = array_sum($conteos);
@endphp

<div class="page-head">
    <div>
        <h1>Alertas de stock</h1>
        <p>Se generan solas cuando una venta o un cambio de umbral deja una prenda por debajo del mínimo, del objetivo o por encima del máximo.</p>
    </div>
    <x-sede-select :sedes="$sedes" :sedeActiva="$sedeActiva" />
</div>

<section class="panel">
    <div class="toolbar">
        <nav class="chips" aria-label="Filtrar alertas">
            @foreach ($chips as $clave => $texto)
                <a href="{{ request()->fullUrlWithQuery(['tipo' => $clave === 'todas' ? null : $clave]) }}" class="chip"
                   @if ($tipo === $clave) aria-current="true" @endif>
                    {{ $texto }} <span class="num">{{ $clave === 'todas' ? $totalActivas : $conteos[$clave] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    @if ($alertas->isEmpty())
        <div class="empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            @if ($totalActivas === 0)
                <h3>No hay alertas activas en esta tienda</h3>
                <p>Todas las prendas están entre su objetivo y su máximo. Si quieres que el aviso salte antes, sube el mínimo en Umbrales.</p>
            @else
                <h3>No hay alertas de tipo “{{ $chips[$tipo] }}”</h3>
                <p>Hay {{ $totalActivas }} alertas de otros tipos.</p>
                <a class="btn" href="{{ request()->fullUrlWithQuery(['tipo' => null]) }}">Ver todas</a>
            @endif
        </div>
    @else
        <ul class="alert-list">
            @foreach ($alertas as $a)
                <li class="alert-item" data-tipo="{{ $a->tipo }}">
                    <div>
                        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                            <x-estado :estado="$a->tipo" />
                            <span class="prod-name">{{ $a->variante->producto->nombre }}</span>
                        </div>
                        <div class="prod-meta">Talla {{ $a->variante->talla }} · {{ $a->variante->color }} · <span class="num">{{ $a->variante->sku }}</span> · desde {{ $a->created_at->diffForHumans() }}</div>
                        <p class="alert-msg">{{ $a->mensaje }}</p>
                    </div>
                    <x-medidor :disponible="$a->stock_disponible_calculado" :minimo="$a->stock_minimo_umbral" :objetivo="$a->stock_objetivo_umbral" :maximo="$a->stock_maximo_umbral" :estado="$a->tipo" leyenda />
                    <div>
                        @if ($a->tipo !== 'sobrestock')
                            <button type="button" class="btn" data-sugerencias="{{ route('alertas.sugerencias', ['varianteId' => $a->variante_id, 'sede_id' => $sedeActiva->id]) }}">
                                Buscar en otras tiendas
                            </button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>

@if ($resueltas->isNotEmpty())
    <section class="panel" style="margin-top:20px">
        <div class="panel-head"><h2>Resueltas recientemente</h2></div>
        <ul class="recent">
            @foreach ($resueltas as $r)
                <li>
                    <span>{{ $r->variante->producto->nombre }} · {{ $r->variante->talla }} · {{ $r->variante->color }}</span>
                    <span class="muted">{{ $r->fecha_resuelta?->diffForHumans() }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif

<dialog id="modal-sugerencias" class="modal" aria-labelledby="sug-titulo">
    <div class="modal-card">
        <div class="modal-head">
            <div>
                <h2 id="sug-titulo">Stock disponible en otras tiendas</h2>
                <p class="muted" id="sug-sub" style="font-size:.875rem;margin-top:2px"></p>
            </div>
            <button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Cerrar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body" id="sug-body" aria-live="polite"></div>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
(function () {
    const dialog = document.getElementById('modal-sugerencias');
    const body = document.getElementById('sug-body');
    const sub = document.getElementById('sug-sub');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-sugerencias]');
        if (!btn) return;

        sub.textContent = '';
        body.innerHTML = '<div style="display:flex;gap:10px;align-items:center" class="muted"><span class="spinner"></span> Consultando el stock de las demás tiendas…</div>';
        window.modal.open(dialog);

        const { ok, data } = await window.api(btn.dataset.sugerencias);

        if (!ok || !data.success) {
            body.innerHTML = '<div class="notice notice-error" role="alert">No se pudo consultar: ' + esc(data.mensaje || 'error de conexión') + '. Inténtalo de nuevo.</div>';
            return;
        }

        const v = data.variante;
        sub.textContent = `${v.producto} · Talla ${v.talla} · ${v.color}`;
        let html = `<p>En esta tienda hay <strong class="num">${v.stock_local_disponible}</strong> disponibles; faltan <strong class="num">${v.deficit_calculado}</strong> para llegar al objetivo de ${v.stock_local_objetivo}.</p>`;

        if (!data.tiene_excedente_externo) {
            html += '<div class="notice notice-info" style="margin-top:12px">Ninguna otra tienda tiene unidades por encima de su máximo. Corresponde pedir reposición al proveedor.</div>';
        } else {
            html += '<ul class="option-list">' + data.sugerencias.map((s) => `
                <li>
                    <div>
                        <div class="prod-name">${esc(s.sede_nombre)}</div>
                        <div class="prod-meta num">${s.stock_disponible} disponibles · máximo ${s.stock_maximo} · sobran ${s.unidades_excedentes}</div>
                    </div>
                    <div style="text-align:right">
                        <div class="disp-big">${s.cantidad_sugerida_transferir}</div>
                        <div class="prod-meta">a transferir</div>
                    </div>
                </li>`).join('') + '</ul>';
            html += '<p class="muted" style="font-size:.8125rem;margin-top:12px">Coordina el traslado con la tienda de origen. La cantidad sugerida cubre el faltante sin dejarla por debajo de su máximo.</p>';
        }
        body.innerHTML = html;
    });
})();
</script>
@endpush
