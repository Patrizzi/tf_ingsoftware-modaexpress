/* ModaExpress Inventario · comportamiento global (vanilla JS, sin dependencias) */
(function () {
    'use strict';

    const root = document.documentElement;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    /* ---------- Tema claro / oscuro ---------- */
    function applyTheme(theme) {
        root.dataset.theme = theme;
        document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
            const dark = theme === 'dark';
            btn.setAttribute('aria-pressed', String(dark));
            btn.querySelector('[data-theme-label]')?.replaceChildren(dark ? 'Tema claro' : 'Tema oscuro');
            btn.setAttribute('aria-label', dark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro');
        });
    }
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-theme-toggle]');
        if (!btn) return;
        const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('mx-theme', next);
        applyTheme(next);
    });
    applyTheme(root.dataset.theme || 'light');

    /* ---------- Menú lateral en móvil ---------- */
    const sidebar = document.getElementById('sidebar');
    const scrim = document.getElementById('scrim');
    const menuBtn = document.getElementById('menu-btn');
    function setMenu(open) {
        if (!sidebar) return;
        sidebar.classList.toggle('is-open', open);
        scrim?.classList.toggle('is-open', open);
        menuBtn?.setAttribute('aria-expanded', String(open));
        if (open) sidebar.querySelector('a, button')?.focus();
    }
    menuBtn?.addEventListener('click', () => setMenu(!sidebar.classList.contains('is-open')));
    scrim?.addEventListener('click', () => setMenu(false));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar?.classList.contains('is-open')) { setMenu(false); menuBtn?.focus(); }
    });

    /* ---------- Toasts ---------- */
    const ICONS = {
        success: '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>',
        error: '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>',
        info: '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/></svg>',
    };
    window.toast = function (message, type = 'info', ms = 4500) {
        const box = document.getElementById('toasts');
        if (!box) return;
        const el = document.createElement('div');
        el.className = 'toast';
        el.dataset.type = type;
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');
        el.innerHTML = ICONS[type] || ICONS.info;
        const text = document.createElement('span');
        text.textContent = message;
        const close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('aria-label', 'Cerrar aviso');
        close.textContent = '×';
        el.append(text, close);
        box.append(el);
        requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('is-in')));

        let timer;
        const dismiss = () => {
            clearTimeout(timer);
            el.classList.add('is-out');
            el.addEventListener('transitionend', () => el.remove(), { once: true });
            setTimeout(() => el.remove(), 300);
        };
        const start = () => { timer = setTimeout(dismiss, ms); };
        close.addEventListener('click', dismiss);
        el.addEventListener('mouseenter', () => clearTimeout(timer));
        el.addEventListener('mouseleave', start);
        start();
    };

    /* ---------- Modal (<dialog>) con animación de entrada y cierre con Escape ---------- */
    window.modal = {
        open(dialog) {
            dialog.showModal();
            const card = dialog.querySelector('.modal-card');
            requestAnimationFrame(() => requestAnimationFrame(() => card?.classList.add('is-in')));
        },
        close(dialog) {
            const card = dialog.querySelector('.modal-card');
            card?.classList.remove('is-in');
            setTimeout(() => dialog.close(), 150);
        },
    };
    document.addEventListener('click', (e) => {
        const closer = e.target.closest('[data-modal-close]');
        if (closer) window.modal.close(closer.closest('dialog'));
        if (e.target.tagName === 'DIALOG' && e.target.open) window.modal.close(e.target); // clic en el fondo
    });
    document.addEventListener('cancel', (e) => {
        if (e.target.tagName === 'DIALOG') { e.preventDefault(); window.modal.close(e.target); }
    }, true);

    /* ---------- Fetch JSON con CSRF ---------- */
    window.api = async function (url, options = {}) {
        const res = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
        });
        let data = {};
        try { data = await res.json(); } catch (_) { /* respuesta sin cuerpo JSON */ }
        if (res.status === 419 || res.status === 401) {
            data.mensaje = 'Tu sesión expiró. Recarga la página e inicia sesión de nuevo.';
        } else if (!data.mensaje && data.message) {
            data.mensaje = data.errors ? Object.values(data.errors)[0][0] : data.message;
        }
        return { ok: res.ok, status: res.status, data };
    };

    window.soles = (n) => 'S/ ' + Number(n).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    /* ---------- Cambiar de sede: envía el formulario al elegir ---------- */
    document.querySelectorAll('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));
})();
