<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Acceso al sistema de inventario de ModaExpress.">
    <title>Iniciar sesión · ModaExpress Inventario</title>
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
<div class="auth">
    <aside class="auth-aside" aria-hidden="true">
        <div class="brand" style="border:0;padding:0;height:auto;color:#fff">
            <span class="brand-mark">ME</span>
            <span class="brand-name">ModaExpress<small style="color:rgba(255,255,255,.7)">Inventario</small></span>
        </div>
        <div>
            <h2>Cada prenda, en cada tienda, en el momento.</h2>
            <p>Cada venta descuenta stock al instante. Cuando una prenda baja de su mínimo, el sistema avisa y sugiere de qué tienda traerla.</p>
            {{-- Ejemplo ilustrativo de cómo se lee el medidor; no son datos reales --}}
            <div class="auth-states">
                <div><div class="lbl"><span>Ejemplo: crítico</span><span>2 de mín. 5</span></div><x-medidor :disponible="2" :minimo="5" :objetivo="15" :maximo="30" estado="critico" /></div>
                <div><div class="lbl"><span>Ejemplo: normal</span><span>21 de obj. 15</span></div><x-medidor :disponible="21" :minimo="5" :objetivo="15" :maximo="30" estado="normal" /></div>
                <div><div class="lbl"><span>Ejemplo: sobrestock</span><span>42 de máx. 30</span></div><x-medidor :disponible="42" :minimo="5" :objetivo="15" :maximo="30" estado="sobrestock" /></div>
            </div>
        </div>
        <small style="color:rgba(255,255,255,.6)">Trabajo final · Ingeniería del Software</small>
    </aside>

    <main class="auth-main">
        <div class="auth-card">
            <div class="brand">
                <span class="brand-mark" aria-hidden="true">ME</span>
                <span class="brand-name">ModaExpress<small>Inventario</small></span>
            </div>

            <h1>Iniciar sesión</h1>
            <p class="muted" style="margin:6px 0 24px">Usa el correo que te asignó tu gerente.</p>

            <form method="POST" action="{{ route('login.store') }}" class="stack" novalidate>
                @csrf
                <div class="field">
                    <label for="email" class="label">Correo</label>
                    <input id="email" name="email" type="email" class="input" autocomplete="username" required autofocus
                           value="{{ old('email') }}" placeholder="nombre@modaexpress.com"
                           @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    @error('email') <span id="email-error" class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label for="password" class="label">Contraseña</label>
                    <input id="password" name="password" type="password" class="input" autocomplete="current-password" required
                           @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                    @error('password') <span id="password-error" class="field-error">{{ $message }}</span> @enderror
                </div>
                <label style="display:flex;align-items:center;gap:8px;font-size:.875rem;min-height:32px">
                    <input type="checkbox" name="remember" value="1"> Mantener sesión en este equipo
                </label>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Entrar</button>
            </form>

            @if (app()->environment('local', 'testing'))
                <div class="demo-users">
                    Usuarios de demostración (contraseña <code>password123</code>):
                    <table>
                        <tr><td>Administrador</td><td><button type="button" data-fill="admin@modaexpress.com">admin@modaexpress.com</button></td></tr>
                        <tr><td>Gerente</td><td><button type="button" data-fill="gerente@modaexpress.com">gerente@modaexpress.com</button></td></tr>
                        <tr><td>Vendedor</td><td><button type="button" data-fill="cajero@modaexpress.com">cajero@modaexpress.com</button></td></tr>
                    </table>
                </div>
                <script>
                    document.querySelectorAll('[data-fill]').forEach(function (b) {
                        b.addEventListener('click', function () {
                            document.getElementById('email').value = b.dataset.fill;
                            document.getElementById('password').value = 'password123';
                            document.getElementById('password').focus();
                        });
                    });
                </script>
            @endif
        </div>
    </main>
</div>
</body>
</html>
