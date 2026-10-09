@props(['estado'])
@php
    $textos = ['critico' => 'Crítico', 'preventivo' => 'Bajo', 'normal' => 'Normal', 'sobrestock' => 'Sobrestock'];
@endphp
<span {{ $attributes->class(['estado', 'estado-'.$estado]) }}>{{ $textos[$estado] ?? ucfirst($estado) }}</span>
