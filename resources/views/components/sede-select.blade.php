@props(['sedes', 'sedeActiva'])
{{-- Solo el administrador ve varias sedes; el resto ve su tienda como texto fijo. --}}
@if ($sedes->count() > 1)
    <form method="GET" class="field" style="min-width: 240px">
        <label for="sede_id" class="label">Tienda</label>
        @foreach (request()->except(['sede_id', 'page']) as $k => $v)
            @if (is_string($v)) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
        @endforeach
        <select id="sede_id" name="sede_id" class="select" data-autosubmit>
            @foreach ($sedes as $s)
                <option value="{{ $s->id }}" @selected($s->id === $sedeActiva->id)>{{ $s->nombre }}</option>
            @endforeach
        </select>
        <noscript><button class="btn btn-sm">Cambiar</button></noscript>
    </form>
@else
    <div class="field">
        <span class="label">Tienda</span>
        <strong>{{ $sedeActiva->nombre }}</strong>
    </div>
@endif
