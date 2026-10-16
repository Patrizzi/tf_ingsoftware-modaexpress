@extends('layouts.app')

@section('title', 'Punto de venta')

@section('content')
@php
    $metodos = ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'yape_plin' => 'Yape / Plin'];
    $tiposComprobante = ['boleta' => 'Boleta', 'factura' => 'Factura'];
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
            <h2 id="pay-title">Cobro y comprobante</h2>

            <div class="totals">
                <div><span class="muted">Base imponible</span><span class="num" id="t-base">S/ 0.00</span></div>
                <div><span class="muted">IGV (18 %)</span><span class="num" id="t-igv">S/ 0.00</span></div>
                <div class="grand"><span>Total</span><span class="num" id="t-total">S/ 0.00</span></div>
            </div>

            <fieldset style="border:0;padding:0;margin:0">
                <legend class="label" style="margin-bottom:8px">Tipo de comprobante</legend>
                <div class="pay-options">
                    @foreach ($tiposComprobante as $valor => $texto)
                        <label class="pay-option">
                            <input type="radio" name="tipo_comprobante" value="{{ $valor }}" @checked($loop->first)>
                            <span>{{ $texto }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="stack" style="gap:10px;margin-top:12px" id="cliente-fields">
                <div class="field">
                    <label for="cliente_documento" class="label" id="lbl-doc">DNI del cliente (opcional)</label>
                    <input id="cliente_documento" type="text" class="input num" maxlength="11" inputmode="numeric" placeholder="8 dígitos (en blanco = Cliente Varios)">
                </div>
                <div class="field">
                    <label for="cliente_nombre" class="label" id="lbl-nom">Nombre del cliente (opcional)</label>
                    <input id="cliente_nombre" type="text" class="input" maxlength="180" placeholder="Cliente Varios">
                </div>
                <div class="field" id="wrap-dir" hidden>
                    <label for="cliente_direccion" class="label">Dirección fiscal (opcional)</label>
                    <input id="cliente_direccion" type="text" class="input" maxlength="255" placeholder="Av. Principal 123, Lima">
                </div>
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

            <button type="button" id="confirm" class="btn btn-primary btn-lg btn-block" disabled>Confirmar y emitir comprobante</button>
            <p class="muted" style="font-size:.8125rem">Al confirmar, el stock se descuenta al instante y se genera la boleta o factura lista para imprimir.</p>
        </section>

        <section class="panel" aria-labelledby="recent-title">
            <div class="panel-head"><h2 id="recent-title">Últimas ventas de la tienda</h2></div>
            <ul class="recent" id="recent">
                @forelse ($ultimas as $v)
                    <li>
                        <div>
                            <span class="num" style="font-weight:600">{{ $v->codigo_comprobante }}</span>
                            <small class="muted" style="display:block;font-size:.75rem">{{ ucfirst($v->tipo_comprobante ?? 'boleta') }} · {{ $v->cliente_nombre ?: 'Cliente Varios' }}</small>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px">
                            <span class="num" style="font-weight:600">S/ {{ number_format($v->total, 2) }}</span>
                            <a href="{{ route('ventas.comprobante', $v->id) }}" target="_blank" class="btn btn-sm" title="Ver o imprimir comprobante">
                                Comprobante
                            </a>
                        </div>
                    </li>
                @empty
                    <li class="muted" data-empty>Aún no hay ventas registradas en esta tienda.</li>
                @endforelse
            </ul>
        </section>
    </aside>
</div>

<dialog id="modal-comprobante" class="modal" aria-labelledby="comp-titulo">
    <div class="modal-card">
        <div class="modal-head">
            <div>
                <h2 id="comp-titulo">Comprobante emitido</h2>
                <p class="muted" id="comp-sub" style="font-size:.875rem;margin-top:2px"></p>
            </div>
            <button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Cerrar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body" id="comp-body" aria-live="polite"></div>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
(function () {
    const SEDE = {{ $sedeActiva->id }};
    const URL_BUSCAR = @json(route('ventas.buscar_codigo'));
    const URL_VENTA = @json(route('ventas.store'));
    const IGV = 0.18;
    const METODOS_NOMBRES = { efectivo: 'Efectivo', tarjeta: 'Tarjeta', yape_plin: 'Yape / Plin' };

    const scan = document.getElementById('scan');
    const feedback = document.getElementById('scan-feedback');
    const cartEl = document.getElementById('cart');
    const btnConfirm = document.getElementById('confirm');
    const btnClear = document.getElementById('cart-clear');

    const inputDoc = document.getElementById('cliente_documento');
    const inputNom = document.getElementById('cliente_nombre');
    const inputDir = document.getElementById('cliente_direccion');
    const lblDoc = document.getElementById('lbl-doc');
    const lblNom = document.getElementById('lbl-nom');
    const wrapDir = document.getElementById('wrap-dir');
    const dialogComp = document.getElementById('modal-comprobante');
    const compTitulo = document.getElementById('comp-titulo');
    const compSub = document.getElementById('comp-sub');
    const compBody = document.getElementById('comp-body');

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /** @type {Map<number, {data: object, qty: number}>} */
    const cart = new Map();
    let busy = false;

    function syncTipoComprobante() {
        const tipo = document.querySelector('input[name="tipo_comprobante"]:checked')?.value || 'boleta';
        const esFactura = tipo === 'factura';
        lblDoc.textContent = esFactura ? 'RUC del cliente (obligatorio, 11 dígitos)' : 'DNI del cliente (opcional)';
        inputDoc.placeholder = esFactura ? 'Ej. 20608945123' : '8 dígitos (en blanco = Cliente Varios)';
        lblNom.textContent = esFactura ? 'Razón Social (obligatorio)' : 'Nombre del cliente (opcional)';
        inputNom.placeholder = esFactura ? 'Ej. Comercial Textil S.A.C.' : 'Cliente Varios';
        wrapDir.hidden = !esFactura;
    }

    document.querySelectorAll('input[name="tipo_comprobante"]').forEach((r) => {
        r.addEventListener('change', syncTipoComprobante);
    });
    syncTipoComprobante();

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

    function mostrarComprobanteModal(v) {
        const esFactura = v.tipo_comprobante === 'factura';
        const tituloDoc = esFactura ? 'FACTURA DE VENTA ELECTRÓNICA' : 'BOLETA DE VENTA ELECTRÓNICA';
        compTitulo.textContent = `${esFactura ? 'Factura' : 'Boleta'} ${v.codigo}`;
        compSub.textContent = `Emitida el ${v.fecha} · ${v.sede.nombre}`;

        const filasHtml = (v.items || []).map((item) => `
            <tr>
                <td class="num">${item.cantidad}</td>
                <td>
                    <div style="font-weight:500">${esc(item.nombre)}</div>
                    <div class="muted" style="font-size:.75rem">SKU ${esc(item.sku)} · Talla ${esc(item.talla)} · ${esc(item.color)}</div>
                </td>
                <td class="r num">${window.soles(item.precio_unitario)}</td>
                <td class="r num" style="font-weight:600">${window.soles(item.subtotal)}</td>
            </tr>
        `).join('');

        compBody.innerHTML = `
            <div class="receipt-preview">
                <div class="receipt-preview-head">
                    <div>
                        <strong>MODAEXPRESS PERÚ S.A.C.</strong>
                        <div class="muted" style="font-size:.75rem">R.U.C. 20608945123 · ${esc(v.sede.direccion || '')}</div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:.75rem;font-weight:600;color:var(--text-muted)">${tituloDoc}</div>
                        <div class="num" style="font-weight:700;font-size:1rem">${esc(v.codigo)}</div>
                    </div>
                </div>

                <div class="receipt-preview-meta">
                    <div><span class="muted">${esFactura ? 'Razón Social:' : 'Cliente:'}</span> <strong>${esc(v.cliente.nombre)}</strong></div>
                    <div><span class="muted">${esFactura ? 'RUC:' : 'Doc:'}</span> <span class="num">${esc(v.cliente.documento)}</span></div>
                    ${v.cliente.direccion ? `<div style="grid-column:1/-1"><span class="muted">Dirección:</span> ${esc(v.cliente.direccion)}</div>` : ''}
                    <div><span class="muted">Pago:</span> ${esc(METODOS_NOMBRES[v.metodo_pago] || v.metodo_pago)}</div>
                    <div><span class="muted">Cajero:</span> ${esc(v.cajero)}</div>
                </div>

                <table class="table" style="font-size:.8125rem;margin-top:10px">
                    <thead>
                        <tr>
                            <th>Cant.</th>
                            <th>Prenda</th>
                            <th class="r">P. Unit.</th>
                            <th class="r">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>${filasHtml}</tbody>
                </table>

                <div class="totals" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border-soft);font-size:.875rem">
                    <div><span class="muted">Op. Gravada (Base)</span><span class="num">${window.soles(v.subtotal)}</span></div>
                    <div><span class="muted">I.G.V. (18 %)</span><span class="num">${window.soles(v.impuesto)}</span></div>
                    <div class="grand" style="font-size:1.25rem"><span>Importe Total</span><span class="num">${window.soles(v.total)}</span></div>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px;flex-wrap:wrap">
                <button type="button" class="btn" data-modal-close>Nueva venta</button>
                <a href="${esc(v.url_comprobante)}?print=1" target="_blank" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                    Imprimir ${esFactura ? 'factura' : 'boleta'}
                </a>
            </div>
        `;

        window.modal.open(dialogComp);
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

        const tipoComprobante = document.querySelector('input[name="tipo_comprobante"]:checked')?.value || 'boleta';
        const doc = inputDoc.value.trim();
        const nom = inputNom.value.trim();
        const dir = inputDir.value.trim();

        if (tipoComprobante === 'factura') {
            if (!/^\d{11}$/.test(doc)) {
                setFeedback('Para emitir <strong>Factura</strong> debes ingresar un RUC válido de 11 dígitos.', 'error');
                inputDoc.focus();
                return;
            }
            if (!nom) {
                setFeedback('Para emitir <strong>Factura</strong> debes ingresar la Razón Social del cliente.', 'error');
                inputNom.focus();
                return;
            }
        }

        busy = true;
        btnConfirm.disabled = true;
        btnConfirm.innerHTML = '<span class="spinner" aria-hidden="true"></span> Emitiendo comprobante…';

        const { ok, data } = await window.api(URL_VENTA, {
            method: 'POST',
            body: JSON.stringify({
                sede_id: SEDE,
                tipo_comprobante: tipoComprobante,
                cliente_documento: doc || null,
                cliente_nombre: nom || null,
                cliente_direccion: tipoComprobante === 'factura' ? (dir || null) : null,
                metodo_pago: document.querySelector('input[name="metodo_pago"]:checked').value,
                items: Array.from(cart.values(), ({ data, qty }) => ({ variante_id: data.id, cantidad: qty })),
            }),
        });

        busy = false;
        btnConfirm.textContent = 'Confirmar y emitir comprobante';

        if (!ok) {
            setFeedback(esc(data.mensaje || 'No se pudo registrar la venta.') + ' No se descontó stock.', 'error');
            render();
            return;
        }

        const v = data.venta;
        const recent = document.getElementById('recent');
        recent.querySelector('[data-empty]')?.remove();
        const li = document.createElement('li');
        const tipoTexto = v.tipo_comprobante === 'factura' ? 'Factura' : 'Boleta';
        li.innerHTML = `
            <div>
                <span class="num" style="font-weight:600">${esc(v.codigo)}</span>
                <small class="muted" style="display:block;font-size:.75rem">${tipoTexto} · ${esc(v.cliente.nombre)}</small>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
                <span class="num" style="font-weight:600">${window.soles(v.total)}</span>
                <a href="${esc(v.url_comprobante)}" target="_blank" class="btn btn-sm">Comprobante</a>
            </div>
        `;
        recent.prepend(li);
        while (recent.children.length > 5) recent.lastElementChild.remove();

        cart.clear();
        inputDoc.value = '';
        inputNom.value = '';
        inputDir.value = '';
        setFeedback('', '');
        render();
        window.toast(`${tipoTexto} ${v.codigo} emitida por ${window.soles(v.total)}.`, 'success');
        mostrarComprobanteModal(v);
    });

    // Atajo: F2 vuelve al campo de escaneo desde cualquier parte
    document.addEventListener('keydown', (e) => { if (e.key === 'F2') { e.preventDefault(); scan.focus(); } });

    render();
})();
</script>
@endpush
