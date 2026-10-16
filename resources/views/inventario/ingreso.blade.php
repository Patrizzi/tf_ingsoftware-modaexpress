@extends('layouts.app')

@section('title', 'Ingreso de mercadería')

@section('content')
@php
    $modoProductoActual = old('modo_producto', 'nuevo');
@endphp

<div class="page-head">
    <div>
        <h1>Ingreso de mercadería</h1>
        <p>Suma unidades a prendas que ya existen en el catálogo o registra una nueva mercadería con su código SKU y sus datos completos.</p>
    </div>
    <x-sede-select :sedes="$sedes" :sedeActiva="$sedeActiva" />
</div>

@if ($errors->any())
    <div class="notice notice-error" role="alert" style="margin-bottom: 20px">
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
        <div>
            <strong>Revisa los datos antes de continuar:</strong>
            <ul style="margin:4px 0 0;padding-left:18px">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<div class="pos">
    <div class="stack">
        <section class="panel">
            <div class="toolbar" style="justify-content: space-between">
                <nav class="chips" role="tablist" aria-label="Modo de ingreso de mercadería">
                    <button type="button" role="tab" id="tab-btn-existente" class="chip"
                            aria-selected="{{ $tab === 'existente' ? 'true' : 'false' }}"
                            aria-current="{{ $tab === 'existente' ? 'true' : 'false' }}"
                            data-tab-target="existente">
                        Mercadería ya existente <span class="num">{{ $variantes->count() }}</span>
                    </button>
                    <button type="button" role="tab" id="tab-btn-nueva" class="chip"
                            aria-selected="{{ $tab === 'nueva' ? 'true' : 'false' }}"
                            aria-current="{{ $tab === 'nueva' ? 'true' : 'false' }}"
                            data-tab-target="nueva">
                        Nueva mercadería (nuevo SKU)
                    </button>
                </nav>
            </div>

            {{-- PANEL 1: MERCADERÍA YA EXISTENTE --}}
            <div id="panel-existente" class="panel-body" role="tabpanel" aria-labelledby="tab-btn-existente" @if ($tab !== 'existente') hidden @endif>
                <form method="POST" action="{{ route('inventario.ingreso.existente') }}" class="stack" autocomplete="off">
                    @csrf
                    <input type="hidden" name="sede_id" value="{{ $sedeActiva->id }}">

                    <div class="field">
                        <label for="buscar-existente" class="label">Buscar o escanear prenda existente (opcional)</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M21 21l-5.2-5.2M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                            <input id="buscar-existente" type="search" class="input" placeholder="Filtra por SKU, código de barras, nombre, talla o color">
                        </div>
                    </div>

                    <div class="field">
                        <label for="variante_id" class="label">Seleccionar mercadería del catálogo</label>
                        <select id="variante_id" name="variante_id" class="select" required>
                            <option value="">— Elige una prenda (SKU · Nombre · Talla · Color) —</option>
                            @foreach ($variantes as $v)
                                <option value="{{ $v->id }}"
                                        data-sku="{{ $v->sku }}"
                                        data-barra="{{ $v->codigo_barra }}"
                                        data-nombre="{{ $v->producto }}"
                                        data-talla="{{ $v->talla }}"
                                        data-color="{{ $v->color }}"
                                        data-precio="{{ number_format($v->precio, 2) }}"
                                        data-fisico="{{ $v->stock_fisico }}"
                                        data-disp="{{ $v->disponible }}"
                                        data-objetivo="{{ $v->stock_objetivo }}"
                                        data-maximo="{{ $v->stock_maximo }}"
                                        @selected((int) old('variante_id') === $v->id)>
                                    {{ $v->sku }} · {{ $v->producto }} ({{ $v->talla }} - {{ $v->color }}) — Stock actual: {{ $v->stock_fisico }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="ficha-existente" class="notice notice-info" hidden>
                        <div style="width:100%">
                            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                                <strong id="fe-nombre"></strong>
                                <span class="num" style="font-weight:600" id="fe-precio"></span>
                            </div>
                            <div class="prod-meta" style="margin-top:2px" id="fe-meta"></div>
                            <div style="display:flex;gap:20px;margin-top:10px;padding-top:10px;border-top:1px solid var(--border-soft);font-size:.8125rem;flex-wrap:wrap">
                                <div>Stock físico actual: <strong class="num" id="fe-fisico">0</strong></div>
                                <div>Disponible real: <strong class="num" id="fe-disp">0</strong></div>
                                <div>Objetivo / Máximo: <span class="num" id="fe-umbrales">15 / 30</span></div>
                                <div>Stock físico tras ingreso: <strong class="num" style="color:var(--normal-text)" id="fe-nuevo">0</strong></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="field">
                            <label for="cantidad_existente" class="label">Cantidad a ingresar (unidades)</label>
                            <input id="cantidad_existente" name="cantidad" type="number" min="1" max="99999" step="1" inputmode="numeric" required
                                   class="input num" value="{{ old('cantidad', 10) }}">
                        </div>
                        <div class="field">
                            <label for="motivo_existente" class="label">Guía de remisión / Motivo (opcional)</label>
                            <input id="motivo_existente" name="motivo" type="text" maxlength="255" class="input"
                                   value="{{ old('motivo') }}" placeholder="Ej. Guía GR01-00452 · Reposición proveedor">
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:6px">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Ingresar mercadería al stock
                        </button>
                    </div>
                </form>
            </div>

            {{-- PANEL 2: NUEVA MERCADERÍA (NUEVO SKU + INFO COMPLETA) --}}
            <div id="panel-nueva" class="panel-body" role="tabpanel" aria-labelledby="tab-btn-nueva" @if ($tab !== 'nueva') hidden @endif>
                <form method="POST" action="{{ route('inventario.ingreso.nueva') }}" class="stack" autocomplete="off">
                    @csrf
                    <input type="hidden" name="sede_id" value="{{ $sedeActiva->id }}">

                    <fieldset style="border:0;padding:0;margin:0">
                        <legend class="label" style="margin-bottom:8px">1. Modelo o estilo de prenda</legend>
                        <div class="pay-options">
                            <label class="pay-option">
                                <input type="radio" name="modo_producto" value="nuevo" @checked($modoProductoActual === 'nuevo')>
                                <span>Crear nuevo modelo de prenda</span>
                            </label>
                            <label class="pay-option">
                                <input type="radio" name="modo_producto" value="existente" @checked($modoProductoActual === 'existente')>
                                <span>Agregar talla/color a modelo existente</span>
                            </label>
                        </div>
                    </fieldset>

                    {{-- Sub-bloque: Modelo Existente --}}
                    <div id="bloque-modelo-existente" class="field" @if ($modoProductoActual !== 'existente') hidden @endif>
                        <label for="producto_id" class="label">Seleccionar modelo existente</label>
                        <select id="producto_id" name="producto_id" class="select">
                            <option value="">— Selecciona el modelo padre —</option>
                            @foreach ($productos as $prod)
                                <option value="{{ $prod->id }}"
                                        data-codigo="{{ $prod->codigo_modelo }}"
                                        data-precio="{{ number_format((float) $prod->precio_base, 2, '.', '') }}"
                                        @selected((int) old('producto_id') === $prod->id)>
                                    {{ $prod->codigo_modelo }} · {{ $prod->nombre }} ({{ $prod->categoria }}) — Base S/ {{ number_format((float) $prod->precio_base, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Sub-bloque: Nuevo Modelo --}}
                    <div id="bloque-modelo-nuevo" class="stack" @if ($modoProductoActual === 'existente') hidden @endif>
                        <div class="form-grid-3">
                            <div class="field">
                                <label for="codigo_modelo" class="label">Código de modelo</label>
                                <input id="codigo_modelo" name="codigo_modelo" type="text" maxlength="50" class="input num"
                                       value="{{ old('codigo_modelo') }}" placeholder="Ej. CAS-DNM">
                            </div>
                            <div class="field" style="grid-column: span 2">
                                <label for="nombre_producto" class="label">Nombre comercial de la prenda</label>
                                <input id="nombre_producto" name="nombre_producto" type="text" maxlength="150" class="input"
                                       value="{{ old('nombre_producto') }}" placeholder="Ej. Casaca Denim Clásica Unisex">
                            </div>
                        </div>
                        <div class="form-grid-2">
                            <div class="field">
                                <label for="categoria" class="label">Categoría</label>
                                <input id="categoria" name="categoria" type="text" list="lista-categorias" maxlength="80" class="input"
                                       value="{{ old('categoria') }}" placeholder="Ej. Casacas, Polos, Vestidos">
                                <datalist id="lista-categorias">
                                    @foreach ($categorias as $cat)
                                        <option value="{{ $cat }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="field">
                                <label for="descripcion" class="label">Descripción o material (opcional)</label>
                                <input id="descripcion" name="descripcion" type="text" maxlength="500" class="input"
                                       value="{{ old('descripcion') }}" placeholder="Ej. 100% algodón tangüis con lavado suave">
                            </div>
                        </div>
                    </div>

                    <hr style="border:0;border-top:1px solid var(--border-soft);margin:4px 0">

                    <div>
                        <h2 style="font-size:.9375rem;margin-bottom:12px">2. Identificación de la variante (SKU, talla, color y precio)</h2>
                        <div class="form-grid-2">
                            <div class="field">
                                <label for="sku" class="label">Código SKU único</label>
                                <input id="sku" name="sku" type="text" maxlength="60" required class="input num"
                                       value="{{ old('sku') }}" placeholder="Ej. CAS-DNM-AZU-M">
                            </div>
                            <div class="field">
                                <label for="codigo_barra" class="label">Código de barras (EAN / Etiqueta)</label>
                                <input id="codigo_barra" name="codigo_barra" type="text" maxlength="60" required class="input num"
                                       value="{{ old('codigo_barra') }}" placeholder="Ej. 775004004001">
                            </div>
                        </div>
                        <div class="form-grid-3" style="margin-top:12px">
                            <div class="field">
                                <label for="talla" class="label">Talla</label>
                                <input id="talla" name="talla" type="text" maxlength="20" required class="input"
                                       value="{{ old('talla') }}" placeholder="Ej. S, M, L, 32">
                            </div>
                            <div class="field">
                                <label for="color" class="label">Color</label>
                                <input id="color" name="color" type="text" maxlength="40" required class="input"
                                       value="{{ old('color') }}" placeholder="Ej. Azul Índigo">
                            </div>
                            <div class="field">
                                <label for="precio" class="label">Precio de venta S/ (inc. IGV)</label>
                                <input id="precio" name="precio" type="number" step="0.01" min="0.10" max="99999.99" required class="input num"
                                       value="{{ old('precio') }}" placeholder="Ej. 149.90">
                            </div>
                        </div>
                    </div>

                    <hr style="border:0;border-top:1px solid var(--border-soft);margin:4px 0">

                    <div>
                        <h2 style="font-size:.9375rem;margin-bottom:12px">3. Stock inicial y umbrales para {{ $sedeActiva->nombre }}</h2>
                        <div class="form-grid-4">
                            <div class="field">
                                <label for="cantidad_nueva" class="label">Cantidad inicial</label>
                                <input id="cantidad_nueva" name="cantidad" type="number" min="0" max="99999" step="1" required class="input num"
                                       value="{{ old('cantidad', 20) }}">
                            </div>
                            <div class="field">
                                <label for="stock_minimo" class="label">Umbral mínimo</label>
                                <input id="stock_minimo" name="stock_minimo" type="number" min="0" step="1" required class="input num"
                                       value="{{ old('stock_minimo', 5) }}">
                            </div>
                            <div class="field">
                                <label for="stock_objetivo" class="label">Stock objetivo</label>
                                <input id="stock_objetivo" name="stock_objetivo" type="number" min="1" step="1" required class="input num"
                                       value="{{ old('stock_objetivo', 15) }}">
                            </div>
                            <div class="field">
                                <label for="stock_maximo" class="label">Stock máximo</label>
                                <input id="stock_maximo" name="stock_maximo" type="number" min="1" step="1" required class="input num"
                                       value="{{ old('stock_maximo', 30) }}">
                            </div>
                        </div>
                        <div class="field" style="margin-top:12px">
                            <label for="motivo_nueva" class="label">Guía de remisión / Observación de ingreso (opcional)</label>
                            <input id="motivo_nueva" name="motivo" type="text" maxlength="255" class="input"
                                   value="{{ old('motivo') }}" placeholder="Ej. Alta de colección de temporada · Guía GR01-00980">
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:6px">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Registrar nueva mercadería e ingresar stock
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <aside class="stack checkout">
        <section class="panel" aria-labelledby="ingresos-recientes-title">
            <div class="panel-head">
                <h2 id="ingresos-recientes-title">Últimos ingresos en sede</h2>
                <a href="{{ route('movimientos.index', ['sede_id' => $sedeActiva->id, 'tipo' => 'ingreso']) }}" class="btn btn-sm btn-ghost">Ver bitácora</a>
            </div>
            <ul class="recent">
                @forelse ($ultimosIngresos as $ing)
                    <li>
                        <div>
                            <div class="prod-name">{{ $ing->variante?->producto?->nombre ?? 'Prenda' }}</div>
                            <div class="prod-meta">
                                <span class="num">{{ $ing->variante?->sku }}</span> · {{ $ing->variante?->talla }} · {{ $ing->variante?->color }}
                            </div>
                            @if ($ing->motivo)
                                <div class="muted" style="font-size:.75rem;margin-top:2px">{{ $ing->motivo }}</div>
                            @endif
                        </div>
                        <div style="text-align:right;white-space:nowrap">
                            <div class="num" style="font-weight:700;color:var(--normal-text)">+{{ $ing->cantidad }}</div>
                            <div class="muted num" style="font-size:.75rem">Stock: {{ $ing->stock_fisico_nuevo }}</div>
                        </div>
                    </li>
                @empty
                    <li class="muted">Aún no se han registrado ingresos de mercadería en esta sede.</li>
                @endforelse
            </ul>
        </section>
    </aside>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // 1. Conmutación de pestañas: Mercadería existente vs Nueva mercadería
    const tabBtns = document.querySelectorAll('[data-tab-target]');
    const panelExistente = document.getElementById('panel-existente');
    const panelNueva = document.getElementById('panel-nueva');

    function activarTab(target) {
        const esNueva = target === 'nueva';
        panelExistente.hidden = esNueva;
        panelNueva.hidden = !esNueva;
        tabBtns.forEach((btn) => {
            const activo = btn.dataset.tabTarget === target;
            btn.setAttribute('aria-selected', String(activo));
            btn.setAttribute('aria-current', String(activo));
        });
    }

    tabBtns.forEach((btn) => {
        btn.addEventListener('click', () => activarTab(btn.dataset.tabTarget));
    });

    // 2. Buscador rápido y vista previa para Mercadería Existente
    const inputBuscar = document.getElementById('buscar-existente');
    const selectVariante = document.getElementById('variante_id');
    const inputCantExistente = document.getElementById('cantidad_existente');
    const ficha = document.getElementById('ficha-existente');
    const opcionesOriginales = Array.from(selectVariante.options).slice(1);

    function actualizarFichaExistente() {
        const opt = selectVariante.selectedOptions[0];
        if (!opt || !opt.value) {
            ficha.hidden = true;
            return;
        }
        const fisico = Number(opt.dataset.fisico || 0);
        const cant = Math.max(0, Number(inputCantExistente.value || 0));
        document.getElementById('fe-nombre').textContent = opt.dataset.nombre;
        document.getElementById('fe-precio').textContent = 'S/ ' + opt.dataset.precio;
        document.getElementById('fe-meta').textContent = `SKU: ${opt.dataset.sku} · Código de barras: ${opt.dataset.barra} · Talla ${opt.dataset.talla} · Color ${opt.dataset.color}`;
        document.getElementById('fe-fisico').textContent = String(fisico);
        document.getElementById('fe-disp').textContent = opt.dataset.disp;
        document.getElementById('fe-umbrales').textContent = `${opt.dataset.objetivo} / ${opt.dataset.maximo}`;
        document.getElementById('fe-nuevo').textContent = String(fisico + cant);
        ficha.hidden = false;
    }

    selectVariante.addEventListener('change', actualizarFichaExistente);
    inputCantExistente.addEventListener('input', actualizarFichaExistente);
    actualizarFichaExistente();

    inputBuscar?.addEventListener('input', () => {
        const q = inputBuscar.value.trim().toLowerCase();
        const valorActual = selectVariante.value;
        selectVariante.length = 1; // conservar placeholder

        let coincidenciaExacta = null;
        opcionesOriginales.forEach((opt) => {
            const texto = `${opt.dataset.sku} ${opt.dataset.barra} ${opt.dataset.nombre} ${opt.dataset.talla} ${opt.dataset.color}`.toLowerCase();
            if (!q || texto.includes(q)) {
                selectVariante.appendChild(opt);
                if (q && (opt.dataset.sku.toLowerCase() === q || opt.dataset.barra.toLowerCase() === q)) {
                    coincidenciaExacta = opt.value;
                }
            }
        });

        if (coincidenciaExacta) {
            selectVariante.value = coincidenciaExacta;
        } else if (Array.from(selectVariante.options).some((o) => o.value === valorActual)) {
            selectVariante.value = valorActual;
        } else if (q && selectVariante.options.length === 2) {
            selectVariante.selectedIndex = 1;
        }
        actualizarFichaExistente();
    });

    // 3. Conmutación en Nueva Mercadería: Nuevo modelo vs Modelo existente + autocompletado de SKU sugerido
    const radiosModo = document.querySelectorAll('input[name="modo_producto"]');
    const bloqueExistente = document.getElementById('bloque-modelo-existente');
    const bloqueNuevo = document.getElementById('bloque-modelo-nuevo');
    const selectProd = document.getElementById('producto_id');
    const inputCodModelo = document.getElementById('codigo_modelo');
    const inputTalla = document.getElementById('talla');
    const inputColor = document.getElementById('color');
    const inputSku = document.getElementById('sku');
    const inputPrecio = document.getElementById('precio');

    function syncModoProducto() {
        const modo = document.querySelector('input[name="modo_producto"]:checked')?.value || 'nuevo';
        const esExistente = modo === 'existente';
        bloqueExistente.hidden = !esExistente;
        bloqueNuevo.hidden = esExistente;
    }

    radiosModo.forEach((r) => r.addEventListener('change', syncModoProducto));
    syncModoProducto();

    let skuEditadoManualmente = Boolean(inputSku.value);
    inputSku.addEventListener('input', () => { skuEditadoManualmente = inputSku.value.trim().length > 0; });

    function sugerirSku() {
        if (skuEditadoManualmente) return;
        const modo = document.querySelector('input[name="modo_producto"]:checked')?.value || 'nuevo';
        const baseModelo = modo === 'existente'
            ? (selectProd.selectedOptions[0]?.dataset.codigo || '')
            : inputCodModelo.value.trim();
        const col = inputColor.value.trim().replace(/[^a-zA-Z0-9]/g, '').slice(0, 3).toUpperCase();
        const tal = inputTalla.value.trim().toUpperCase();
        const partes = [baseModelo.toUpperCase(), col, tal].filter(Boolean);
        if (partes.length >= 2) {
            inputSku.value = partes.join('-');
        }
    }

    selectProd?.addEventListener('change', () => {
        const opt = selectProd.selectedOptions[0];
        if (opt && opt.dataset.precio && !inputPrecio.value) {
            inputPrecio.value = opt.dataset.precio;
        }
        sugerirSku();
    });
    inputCodModelo?.addEventListener('input', sugerirSku);
    inputColor?.addEventListener('input', sugerirSku);
    inputTalla?.addEventListener('input', sugerirSku);
})();
</script>
@endpush
