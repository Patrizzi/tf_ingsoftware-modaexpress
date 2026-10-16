<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ strtoupper($venta->tipo_comprobante ?? 'boleta') }} {{ $venta->codigo_comprobante }} · ModaExpress</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="receipt-page">
@php
    $esFactura = ($venta->tipo_comprobante ?? 'boleta') === 'factura';
    $tituloDoc = $esFactura ? 'FACTURA DE VENTA ELECTRÓNICA' : 'BOLETA DE VENTA ELECTRÓNICA';
    $tipoDocCliente = $esFactura ? 'RUC' : 'DNI / Doc.';
    $metodosNombres = [
        'efectivo' => 'Efectivo',
        'tarjeta' => 'Tarjeta',
        'yape_plin' => 'Yape / Plin',
        'pasarela' => 'Pasarela Online',
    ];
@endphp

<div class="receipt-toolbar no-print">
    <a href="{{ route('ventas.pos', ['sede_id' => $venta->sede_id]) }}" class="btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        Volver al Punto de venta
    </a>
    <button type="button" class="btn btn-primary" onclick="window.print()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
        Imprimir comprobante
    </button>
</div>

<article class="receipt-sheet" aria-label="{{ $tituloDoc }} {{ $venta->codigo_comprobante }}">
    <header class="receipt-header">
        <div class="receipt-brand">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                <span class="brand-mark" aria-hidden="true">ME</span>
                <strong style="font-size:1.15rem;letter-spacing:-0.02em">MODAEXPRESS PERÚ S.A.C.</strong>
            </div>
            <div class="muted" style="font-size:.8125rem">
                <div>{{ $venta->sede?->nombre ?? 'Tienda Principal' }}</div>
                @if ($venta->sede?->direccion)
                    <div>{{ $venta->sede->direccion }}</div>
                @endif
                @if ($venta->sede?->telefono)
                    <div>Tel: {{ $venta->sede->telefono }}</div>
                @endif
            </div>
        </div>

        <div class="receipt-box">
            <div class="num" style="font-weight:600">R.U.C. 20608945123</div>
            <div class="receipt-doc-title">{{ $tituloDoc }}</div>
            <div class="num receipt-doc-code">{{ $venta->codigo_comprobante }}</div>
        </div>
    </header>

    <section class="receipt-meta" aria-label="Datos del cliente y de la operación">
        <div>
            <span class="muted">{{ $esFactura ? 'Razón Social:' : 'Cliente:' }}</span>
            <strong>{{ $venta->cliente_nombre ?: 'Cliente Varios' }}</strong>
        </div>
        <div>
            <span class="muted">{{ $tipoDocCliente }}:</span>
            <span class="num">{{ $venta->cliente_documento ?: '00000000' }}</span>
        </div>
        @if ($venta->cliente_direccion)
            <div style="grid-column: 1 / -1">
                <span class="muted">Dirección:</span>
                <span>{{ $venta->cliente_direccion }}</span>
            </div>
        @endif
        <div>
            <span class="muted">Fecha de emisión:</span>
            <span class="num">{{ $venta->created_at?->format('d/m/Y H:i:s') }}</span>
        </div>
        <div>
            <span class="muted">Forma de pago:</span>
            <span>{{ $metodosNombres[$venta->metodo_pago] ?? ucfirst($venta->metodo_pago) }}</span>
        </div>
        <div>
            <span class="muted">Atendido por:</span>
            <span>{{ $venta->user?->name ?? 'Caja' }}</span>
        </div>
        <div>
            <span class="muted">Moneda:</span>
            <span>Soles (PEN)</span>
        </div>
    </section>

    <table class="receipt-table">
        <thead>
            <tr>
                <th scope="col">Cant.</th>
                <th scope="col">SKU</th>
                <th scope="col">Descripción</th>
                <th scope="col" class="r">P. Unit.</th>
                <th scope="col" class="r">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($venta->detalles as $d)
                <tr>
                    <td class="num">{{ $d->cantidad }}</td>
                    <td class="num">{{ $d->variante?->sku ?? '-' }}</td>
                    <td>
                        <div>{{ $d->variante?->producto?->nombre ?? 'Prenda' }}</div>
                        <div class="muted" style="font-size:.75rem">Talla {{ $d->variante?->talla }} · {{ $d->variante?->color }}</div>
                    </td>
                    <td class="r num">S/ {{ number_format((float) $d->precio_unitario, 2) }}</td>
                    <td class="r num" style="font-weight:600">S/ {{ number_format((float) $d->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="receipt-totals" aria-label="Desglose de importes">
        <div class="receipt-totals-row">
            <span class="muted">Op. Gravada (Base Imponible)</span>
            <span class="num">S/ {{ number_format((float) $venta->subtotal, 2) }}</span>
        </div>
        <div class="receipt-totals-row">
            <span class="muted">I.G.V. (18 %)</span>
            <span class="num">S/ {{ number_format((float) $venta->impuesto, 2) }}</span>
        </div>
        <div class="receipt-totals-row grand">
            <span>IMPORTE TOTAL</span>
            <span class="num">S/ {{ number_format((float) $venta->total, 2) }}</span>
        </div>
    </section>

    <footer class="receipt-footer">
        <p>Representación impresa de la {{ $esFactura ? 'Factura de Venta Electrónica' : 'Boleta de Venta Electrónica' }}.</p>
        <p class="muted">Conserve su comprobante para cambios o devoluciones dentro de los 7 días calendario con etiquetas intactas.</p>
    </footer>
</article>

@if (request()->boolean('print'))
<script>
    window.addEventListener('load', function () {
        window.print();
    });
</script>
@endif
</body>
</html>
