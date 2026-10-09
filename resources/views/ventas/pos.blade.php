@extends('layouts.app')

@section('title', 'Punto de venta')

@section('content')
@php
    $metodos = ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'yape_plin' => 'Yape / Plin'];
@endphp

<div class="page-head">
    <div>
        <h1>Punto de venta</h1>
        <p>Escanea la etiqueta. Si escaneas la misma prenda otra vez, suma una unidad.</p>
    </div>
    <x-sede-select :sedes="$sedes" :sedeActiva="$sedeActiva" />
</div>

<div class="pos">
    <div class="stack">
        <section class="panel panel-body scan">
            <form id="scan-form" autocomplete="off">
                <label for="scan" class="label" style="display:block;margin-bottom:8px">Código de barras o SKU</label>
                <div class="input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" d="M3.75 4.5v15M6.75 4.5v15M9.75 4.5v15M14.25 4.5v15M17.25 4.5v15M20.25 4.5v15"/></svg>
                    <input id="scan" class="input scan-input num" inputmode="text" autofocus placeholder="Escanea o escribe y presiona Enter" aria-describedby="scan-hint">
                </div>
                <p id="scan-hint" class="scan-hint">El lector escribe el código y presiona Enter solo. Si la etiqueta está dañada, escribe el SKU impreso.</p>
                <div id="scan-feedback" class="scan-feedback" aria-live="polite"></div>
            </form>
        </section>

        <section class="panel" aria-labelledby="cart-title">
            <div class="panel-head">
                <h2 id="cart-title">Venta actual</h2>
                <button type="button" id="cart-clear" class="btn btn-sm btn-ghost" hidden>Vaciar</button>
            </div>
            <div id="cart"></div>
        </section>
    </div>

    <aside class="stack checkout">
        <section class="panel panel-body stack" aria-labelledby="pay-title">
            <h2 id="pay-title">Cobro</h2>
            <div class="totals">
                <div><span class="muted">Base imponible</span><span class="num" id="t-base">S/ 0.00</span></div>
                <div><span class="muted">IGV (18 %)</span><span class="num" id="t-igv">S/ 0.00</span></div>
                <div class="grand"><span>Total</span><span class="num" id="t-total">S/ 0.00</span></div>
            </div>
            <fieldset style="border:0;padding:0;margin:0">
                <legend class="label" style="margin-bottom:8px">Forma de pago</legend>
                <div class="pay-options">
                    @foreach ($metodos as $valor => $texto)
                        <label class="pay-option">
                            <input type="radio" name="metodo_pago" value="{{ $valor }}" @checked($loop->first)>
                            <span>{{ $texto }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <button type="button" id="confirm" class="btn btn-primary btn-lg btn-block" disabled>Confirmar venta</button>
            <p class="muted" style="font-size:.8125rem">Al confirmar, el stock de cada prenda se descuenta en el mismo momento. Si alguna ya no tiene stock, no se cobra nada.</p>
        </section>

        <section class="panel" aria-labelledby="recent-title">
            <div class="panel-head"><h2 id="recent-title">Últimas ventas de la tienda</h2></div>
            <ul class="recent" id="recent">
                @forelse ($ultimas as $v)
                    <li><span class="num">{{ $v->codigo_comprobante }}</span><span class="num">S/ {{ number_format($v->total, 2) }}</span></li>
                @empty
                    <li class="muted" data-empty>Aún no hay ventas registradas en esta tienda.</li>
                @endforelse
            </ul>
        </section>
    </aside>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const SEDE = {{ $sedeActiva->id }};
    const URL_BUSCAR = @json(route('ventas.buscar_codigo'));
    const URL_VENTA = @json(route('ventas.store'));
    const IGV = 0.18;

    const scan = document.getElementById('scan');
    const feedback = document.getElementById('scan-feedback');
    const cartEl = document.getElementById('cart');
    const btnConfirm = document.getElementById('confirm');
    const btnClear = document.getElementById('cart-clear');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /** @type {Map<number, {data: object, qty: number}>} */
    const cart = new Map();
    let busy = false;

    function setFeedback(html, type) {
        feedback.innerHTML = html ? `<div class="notice notice-${type}" ${type === 'error' ? 'role="alert"' : ''}>${html}</div>` : '';
    }

    function render() {
        if (cart.size === 0) {
            cartEl.innerHTML = '<div class="empty" style="padding:36px 24px"><h3>Todavía no hay prendas</h3><p>Escanea la primera etiqueta para empezar la venta.</p></div>';
        } else {
            cartEl.innerHTML = Array.from(cart.values()).map(({ data: p, qty }) => {
                const restante = p.disponible_real - qty;
                const aviso = p.stock_minimo !== null && restante <= p.stock_minimo
                    ? `<div class="line-warn">Tras esta venta quedarán ${restante}: por debajo del mínimo (${p.stock_minimo}).</div>` : '';
                return `<div class="cart-line">
                    <div>
                        <div class="prod-name">${esc(p.producto)}</div>
                        <div class="prod-meta">Talla ${esc(p.talla)} · ${esc(p.color)} · ${window.soles(p.precio)} c/u · ${p.disponible_real} disponibles</div>
                        ${aviso}
                    </div>
                    <div class="stepper" role="group" aria-label="Cantidad de ${esc(p.producto)}">
                        <button type="button" data-dec="${p.id}" aria-label="Quitar una unidad">−</button>
                        <output aria-live="polite">${qty}</output>
                        <button type="button" data-inc="${p.id}" aria-label="Agregar una unidad" ${qty >= p.disponible_real ? 'disabled' : ''}>+</button>
                    </div>
                    <div class="line-total">${window.soles(qty * p.precio)}</div>
                    <button type="button" class="btn btn-ghost btn-icon" data-del="${p.id}" aria-label="Quitar ${esc(p.producto)} de la venta">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>`;
            }).join('');
        }

        // Precio de etiqueta incluye IGV (mismo cálculo que el servidor)
        const total = Array.from(cart.values()).reduce((s, { data, qty }) => s + data.precio * qty, 0);
        const base = Math.round(total / (1 + IGV) * 100) / 100;
        document.getElementById('t-total').textContent = window.soles(total);
        document.getElementById('t-base').textContent = window.soles(base);
        document.getElementById('t-igv').textContent = window.soles(total - base);
        btnConfirm.disabled = cart.size === 0 || busy;
        btnClear.hidden = cart.size === 0;
    }

    function add(p) {
        const line = cart.get(p.id);
        const qty = (line?.qty || 0) + 1;
        if (qty > p.disponible_real) {
            setFeedback(`No hay más unidades de <strong>${esc(p.producto)}</strong> talla ${esc(p.talla)}: quedan ${p.disponible_real} disponibles.`, 'error');
            return;
        }
        cart.set(p.id, { data: p, qty });
        setFeedback(`Agregado: <strong>${esc(p.producto)}</strong> · ${esc(p.talla)} · ${esc(p.color)}`, 'success');
        render();
    }

    document.getElementById('scan-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const codigo = scan.value.trim();
        if (!codigo) return;
        scan.value = '';

        const { ok, data } = await window.api(`${URL_BUSCAR}?codigo=${encodeURIComponent(codigo)}&sede_id=${SEDE}`);
        if (!ok) { setFeedback(esc(data.mensaje || 'No se pudo buscar el código. Revisa la conexión.'), 'error'); scan.focus(); return; }

        const p = data.data;
        if (!p.registrado_en_sede) setFeedback(`<strong>${esc(p.producto)}</strong> no está registrado en el inventario de esta tienda.`, 'error');
        else if (p.disponible_real <= 0) setFeedback(`<strong>${esc(p.producto)}</strong> talla ${esc(p.talla)} está agotado en esta tienda. Revisa Alertas para ver si otra tienda tiene.`, 'error');
        else add(p);
        scan.focus();
    });

    cartEl.addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        const id = Number(b.dataset.inc || b.dataset.dec || b.dataset.del);
        const line = cart.get(id);
        if (!line) return;
        if (b.dataset.inc) line.qty = Math.min(line.qty + 1, line.data.disponible_real);
        if (b.dataset.dec) line.qty -= 1;
        if (b.dataset.del || line.qty <= 0) { cart.delete(id); scan.focus(); }
        render();
    });

    btnClear.addEventListener('click', () => { cart.clear(); setFeedback('', ''); render(); scan.focus(); });

    btnConfirm.addEventListener('click', async () => {
        if (cart.size === 0 || busy) return;
        busy = true;
        btnConfirm.disabled = true;
        btnConfirm.innerHTML = '<span class="spinner" aria-hidden="true"></span> Registrando…';

        const { ok, data } = await window.api(URL_VENTA, {
            method: 'POST',
            body: JSON.stringify({
                sede_id: SEDE,
                metodo_pago: document.querySelector('input[name="metodo_pago"]:checked').value,
                items: Array.from(cart.values(), ({ data, qty }) => ({ variante_id: data.id, cantidad: qty })),
            }),
        });

        busy = false;
        btnConfirm.textContent = 'Confirmar venta';

        if (!ok) {
            setFeedback(esc(data.mensaje || 'No se pudo registrar la venta.') + ' No se descontó stock.', 'error');
            render();
            return;
        }

        const recent = document.getElementById('recent');
        recent.querySelector('[data-empty]')?.remove();
        const li = document.createElement('li');
        li.innerHTML = `<span class="num">${esc(data.venta.codigo)}</span><span class="num">${window.soles(data.venta.total)}</span>`;
        recent.prepend(li);
        while (recent.children.length > 5) recent.lastElementChild.remove();

        cart.clear();
        setFeedback('', '');
        render();
        window.toast(`Venta ${data.venta.codigo} registrada por ${window.soles(data.venta.total)}. Stock actualizado.`, 'success');
        scan.focus();
    });

    // Atajo: F2 vuelve al campo de escaneo desde cualquier parte
    document.addEventListener('keydown', (e) => { if (e.key === 'F2') { e.preventDefault(); scan.focus(); } });

    render();
})();
</script>
@endpush
