@php
    $user = auth()->user();
    $criticas = \App\Models\Alerta::activas()->criticas()
        ->when(! $user->isAdmin(), fn ($q) => $q->where('sede_id', $user->sede_id))
        ->count();
    $roles = ['admin' => 'Administrador', 'gerente' => 'Gerente de tienda', 'vendedor' => 'Vendedor'];
    $iniciales = collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
    $nav = [
        ['grupo' => 'Tienda', 'items' => [
            ['ruta' => 'ventas.pos', 'activo' => 'ventas.*', 'texto' => 'Punto de venta', 'icono' => 'M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5'],
            ['ruta' => 'stock.index', 'activo' => 'stock.*', 'texto' => 'Stock', 'icono' => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z'],
            ['ruta' => 'alertas.index', 'activo' => 'alertas.*', 'texto' => 'Alertas', 'contador' => $criticas, 'icono' => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0'],
        ]],
    ];
    if ($user->isGerente()) {
        $nav[] = ['grupo' => 'Gestión', 'items' => [
            ['ruta' => 'inventario.ingreso', 'activo' => 'inventario.ingreso*', 'texto' => 'Ingreso de mercadería', 'icono' => 'M12 4.5v15m7.5-7.5h-15'],
            ['ruta' => 'inventario.umbrales', 'activo' => 'inventario.umbral*', 'texto' => 'Umbrales', 'icono' => 'M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75'],
            ['ruta' => 'movimientos.index', 'activo' => 'movimientos.*', 'texto' => 'Movimientos', 'icono' => 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z'],
        ]];
    }
@endphp
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Control de inventario en tiempo real de ModaExpress: stock por tienda, alertas por umbral y punto de venta.">
    <title>@yield('title') · ModaExpress Inventario</title>
    {{-- Aplicar el tema antes del primer pintado evita el destello de tema incorrecto --}}
    <script>
        (function () {
            var t = localStorage.getItem('mx-theme');
            if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.dataset.theme = t;
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<a href="#contenido" class="skip-link">Ir al contenido</a>

<div class="shell">
    <aside id="sidebar" class="sidebar" aria-label="Navegación principal">
        <a href="{{ route('home') }}" class="brand">
            <span class="brand-mark" aria-hidden="true">ME</span>
            <span class="brand-name">ModaExpress<small>Inventario</small></span>
        </a>

        <nav class="nav">
            @foreach ($nav as $grupo)
                <div class="nav-group">
                    <div class="nav-label">{{ $grupo['grupo'] }}</div>
                    @foreach ($grupo['items'] as $item)
                        <a href="{{ route($item['ruta']) }}" class="nav-link"
                           @if (request()->routeIs($item['activo'])) aria-current="page" @endif>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icono'] }}"/></svg>
                            {{ $item['texto'] }}
                            @if (! empty($item['contador']))
                                <span class="nav-count" aria-label="{{ $item['contador'] }} alertas críticas">{{ $item['contador'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        <div class="user-box">
            <div class="user-row">
                <span class="avatar" aria-hidden="true">{{ $iniciales }}</span>
                <div class="user-meta">
                    <strong>{{ $user->name }}</strong>
                    <span>{{ $roles[$user->rol] ?? $user->rol }}@if ($user->sede) · {{ str_replace('ModaExpress - ', '', $user->sede->nombre) }}@endif</span>
                </div>
            </div>
            <div class="user-actions">
                <button type="button" class="btn btn-sm" data-theme-toggle aria-pressed="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/></svg>
                    <span data-theme-label>Tema oscuro</span>
                </button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-block">Salir</button>
                </form>
            </div>
        </div>
    </aside>
    <div id="scrim" class="scrim"></div>

    <div class="main">
        <header class="topbar">
            <button type="button" id="menu-btn" class="btn btn-ghost btn-icon menu-btn" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
            </button>
            <span class="topbar-title">@yield('title')</span>
            <span class="topbar-spacer"></span>
            @isset($sedeActiva)
                <span class="muted" style="font-size: .875rem">{{ $sedeActiva->nombre }}</span>
            @endisset
        </header>

        <main id="contenido" class="content" tabindex="-1">
            @if (session('success'))
                <div class="notice notice-success" role="status" style="margin-bottom: 20px">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="notice notice-error" role="alert" style="margin-bottom: 20px">{{ session('error') }}</div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<div id="toasts" class="toasts" aria-live="polite"></div>

<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
@stack('scripts')
</body>
</html>
