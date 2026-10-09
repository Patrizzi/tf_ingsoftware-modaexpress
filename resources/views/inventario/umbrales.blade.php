@extends('layouts.app')

@section('title', 'Umbrales')

@section('content')
@php
    // Fila que acaba de fallar la validación: se reabre con lo que escribió el usuario
    $filaConError = (int) old('fila');
@endphp

<div class="page-head">
    <div>
        <h1>Umbrales de stock</h1>
        <p>Define por prenda cuándo avisar. <strong>Mínimo</strong>: alerta crítica. <strong>Objetivo</strong>: nivel ideal, por debajo hay alerta preventiva. <strong>Máximo</strong>: por encima hay sobrestock.</p>
    </div>
    <x-sede-select :sedes="$sedes" :sedeActiva="$sedeActiva" />
</div>

<section class="panel">
    <form method="GET" class="toolbar" role="search">
        <input type="hidden" name="sede_id" value="{{ $sedeActiva->id }}">
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

    @if ($inventarios->isEmpty())
        <div class="empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" d="M21 21l-5.2-5.2M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
            @if ($busqueda !== '')
                <h3>Ninguna prenda coincide con “{{ $busqueda }}”</h3>
                <p>Revisa la escritura o busca por el código de barras.</p>
            @else
                <h3>Esta tienda no tiene prendas registradas</h3>
                <p>Los umbrales se configuran una vez que la prenda existe en el inventario de la sede.</p>
            @endif
        </div>
    @else
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                <tr>
                    <th scope="col">Prenda</th>
                    <th scope="col" class="r">Disponible</th>
                    <th scope="col" style="min-width:180px">Vista previa</th>
                    <th scope="col" class="r">Mínimo</th>
                    <th scope="col" class="r">Objetivo</th>
                    <th scope="col" class="r">Máximo</th>
                    <th scope="col"><span class="sr-only">Acción</span></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($inventarios as $f)
                    @php
                        $err = $filaConError === $f->inventario_id;
                        $v = fn ($campo) => $err ? old($campo) : $f->{$campo};
                        $formId = 'umbral-'.$f->inventario_id;
                    @endphp
                    <tr data-umbral-row data-disponible="{{ $f->disponible }}">
                        <td>
                            <div class="prod-name">{{ $f->producto }}</div>
                            <div class="prod-meta">Talla {{ $f->talla }} · {{ $f->color }} · <span class="num">{{ $f->sku }}</span></div>
                            @if ($err && $errors->any())
                                <div class="field-error" role="alert" style="margin-top:4px">{{ $errors->first() }}</div>
                            @endif
                        </td>
                        <td class="r"><span class="disp-big">{{ $f->disponible }}</span></td>
                        <td data-col="gauge" data-preview>
                            <x-medidor :disponible="$f->disponible" :minimo="$f->stock_minimo" :objetivo="$f->stock_objetivo" :maximo="$f->stock_maximo" :estado="$f->estado" />
                            <div style="margin-top:2px"><x-estado :estado="$f->estado" data-preview-estado /></div>
                        </td>
                        @foreach (['stock_minimo' => 'Mínimo', 'stock_objetivo' => 'Objetivo', 'stock_maximo' => 'Máximo'] as $campo => $texto)
                            <td class="r">
                                <label for="{{ $formId }}-{{ $campo }}" class="sr-only">{{ $texto }} de {{ $f->sku }}</label>
                                <input id="{{ $formId }}-{{ $campo }}" form="{{ $formId }}" name="{{ $campo }}" type="number" min="0" step="1" inputmode="numeric" required
                                       class="input input-num" value="{{ $v($campo) }}" data-campo="{{ $campo }}"
                                       @if ($err && $errors->has($campo)) aria-invalid="true" @endif>
                            </td>
                        @endforeach
                        <td>
                            <form id="{{ $formId }}" method="POST" action="{{ route('inventario.umbral.update', $f->inventario_id) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="fila" value="{{ $f->inventario_id }}">
                                <button type="submit" class="btn btn-sm" data-guardar disabled>Guardar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @if ($inventarios->hasPages())
            <nav class="pager" aria-label="Paginación">
                <span class="muted">{{ $inventarios->firstItem() }}–{{ $inventarios->lastItem() }} de {{ $inventarios->total() }} prendas</span>
                <span style="display:flex;gap:8px">
                    @if ($inventarios->onFirstPage())
                        <span class="btn btn-sm" aria-disabled="true" style="opacity:.5">Anterior</span>
                    @else
                        <a class="btn btn-sm" href="{{ $inventarios->previousPageUrl() }}">Anterior</a>
                    @endif
                    @if ($inventarios->hasMorePages())
                        <a class="btn btn-sm" href="{{ $inventarios->nextPageUrl() }}">Siguiente</a>
                    @else
                        <span class="btn btn-sm" aria-disabled="true" style="opacity:.5">Siguiente</span>
                    @endif
                </span>
            </nav>
        @endif
    @endif
</section>
@endsection

@push('scripts')
<script>
/* Vista previa en vivo: al escribir un umbral, el medidor y el estado muestran cómo quedaría la prenda.
   El botón Guardar solo se activa si hubo cambios y la regla mínimo < objetivo <= máximo se cumple. */
(function () {
    const textos = { critico: 'Crítico', preventivo: 'Bajo', normal: 'Normal', sobrestock: 'Sobrestock' };

    document.querySelectorAll('[data-umbral-row]').forEach((row) => {
        const disp = Number(row.dataset.disponible);
        const inputs = row.querySelectorAll('[data-campo]');
        const btn = row.querySelector('[data-guardar]');
        const gauge = row.querySelector('[data-preview] .gauge');
        const label = row.querySelector('[data-preview-estado]');
        const original = Array.from(inputs, (i) => i.value).join('|');

        function update() {
            const [min, obj, max] = Array.from(inputs, (i) => Number(i.value));
            const valido = inputs[0].value !== '' && inputs[1].value !== '' && inputs[2].value !== '' && min >= 0 && min < obj && obj <= max;
            inputs[1].setAttribute('aria-invalid', String(inputs[1].value !== '' && obj <= min));
            inputs[2].setAttribute('aria-invalid', String(inputs[2].value !== '' && max < obj));
            btn.disabled = !valido || Array.from(inputs, (i) => i.value).join('|') === original;
            btn.title = valido ? '' : 'Debe cumplirse: mínimo < objetivo ≤ máximo';
            if (!valido) return;

            const estado = disp <= min ? 'critico' : disp <= obj ? 'preventivo' : disp > max ? 'sobrestock' : 'normal';
            const escala = Math.max(max * 1.2, disp, 1);
            const pct = (v) => (Math.min(v, escala) / escala * 100).toFixed(2) + '%';
            gauge.className = 'gauge gauge-' + estado;
            gauge.querySelector('.gauge-fill').style.width = pct(disp);
            const ticks = gauge.querySelectorAll('.gauge-tick');
            [min, obj, max].forEach((v, i) => { ticks[i].style.left = pct(v); });
            gauge.setAttribute('aria-label', `${disp} disponibles, estado ${textos[estado].toLowerCase()}. Mínimo ${min}, objetivo ${obj}, máximo ${max}.`);
            label.className = 'estado estado-' + estado;
            label.textContent = textos[estado];
        }
        inputs.forEach((i) => i.addEventListener('input', update));
        if (row.querySelector('[aria-invalid="true"]')) update();
    });
})();
</script>
@endpush
