@props(['disponible', 'minimo', 'objetivo', 'maximo', 'estado', 'leyenda' => false])
@php
    // Escala: 20 % por encima del máximo para que el sobrestock se vea pasar la última marca
    $escala = max($maximo * 1.2, $disponible, 1);
    $pct = fn ($v) => round(min($v, $escala) / $escala * 100, 2);
    $etiquetas = ['critico' => 'crítico', 'preventivo' => 'bajo', 'normal' => 'normal', 'sobrestock' => 'sobrestock'];
@endphp
<div {{ $attributes->class('gauge-box') }}>
    <div class="gauge gauge-{{ $estado }}" role="img"
         aria-label="{{ $disponible }} disponibles, estado {{ $etiquetas[$estado] ?? $estado }}. Mínimo {{ $minimo }}, objetivo {{ $objetivo }}, máximo {{ $maximo }}.">
        <div class="gauge-track"><div class="gauge-fill" style="width: {{ $pct($disponible) }}%"></div></div>
        <span class="gauge-tick" style="left: {{ $pct($minimo) }}%" title="Mínimo {{ $minimo }}"></span>
        <span class="gauge-tick obj" style="left: {{ $pct($objetivo) }}%" title="Objetivo {{ $objetivo }}"></span>
        <span class="gauge-tick" style="left: {{ $pct($maximo) }}%" title="Máximo {{ $maximo }}"></span>
    </div>
    @if ($leyenda)
        <div class="gauge-legend" aria-hidden="true">
            <span>Mín {{ $minimo }}</span><span>Obj {{ $objetivo }}</span><span>Máx {{ $maximo }}</span>
        </div>
    @endif
</div>
