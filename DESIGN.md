# DESIGN.md · ModaExpress Inventario

Dirección visual entregada por el propietario del proyecto. Este archivo la transcribe y fija cómo se aplica.

Dial: ENERGY 1 / RHYTHM 1 / MOTION 1

> Lectura: herramienta interna de inventario para personal de tienda y gerentes de una PYME de retail, en lenguaje minimalista y profesional, dial ENERGY 1 / RHYTHM 1 / MOTION 1.

## Tono

Minimalista y profesional. La interfaz la usan cajeros y personal de tienda con baja alfabetización digital, así que cada pantalla responde a una sola pregunta y la acción principal es la más visible.

## Paleta

| Token | Claro | Uso |
| --- | --- | --- |
| `brand-blue` | `#2641f8` | Acento único: marca (isotipo) e ítem de navegación activo. Nada más. |
| `primary-btn` | `#1a5eb3` | Botón principal y enlaces de acción. |
| `primary-btn-hover` | `#1a3bb3` | Hover / pressed del botón principal. |
| `primary-navy` | `#1e3a8a` | Scrollbars y acentos de navegación. |
| `app-bg` | `#f3f3f4` | Fondo de la aplicación. |
| `card-bg` | `#ffffff` | Superficies (tablas, paneles, modales). |
| `border-soft` | `#e7eaec` | Bordes y separadores. |
| `text-main` | `#2c3e50` | Títulos y datos. |
| `text-muted` | `#676a6c` | Etiquetas secundarias. (`#888888` no se usa para texto: no llega a 4.5:1 sobre blanco.) |

### Colores semánticos (solo estados de stock)

Los cuatro colores semánticos se reservan para los estados del inventario. No decoran nada más.

| Token | Base | Estado de stock |
| --- | --- | --- |
| `danger-coral` | `#ed5565` | Crítico (disponible ≤ mínimo) y errores |
| `warning-amber` | `#f8ac59` | Bajo (mínimo < disponible ≤ objetivo) |
| `info-cyan` | `#23c6c8` | Sobrestock (disponible > máximo) |
| `success-emerald` | `#1ab394` | Normal y confirmaciones |

Los tonos base no alcanzan 4.5:1 como texto sobre blanco, así que se usan como **relleno** (punto, barra, fondo tenue) y el texto de cada estado usa una variante oscurecida del mismo matiz. El estado nunca se comunica solo con color: siempre va acompañado de texto ("Crítico", "Bajo", "Normal", "Sobrestock").

## Tipografía

`Outfit, system-ui, -apple-system, sans-serif`, elegida por el propietario. Títulos en semibold/bold con tracking negativo; etiquetas de formulario compactas (`text-xs` / `text-sm`) con tracking neutro. Las cifras de stock usan `font-variant-numeric: tabular-nums` para que las columnas queden alineadas.

## Iconografía

Heroicons outline (SVG en línea), 20px en navegación y 16–20px en botones. Cada icono acompaña a un texto; no hay iconos decorativos sueltos.

## Tema

Claro por defecto, con interruptor a oscuro. Los dos modos tienen que funcionar completos.

## Motivo de identidad

**Medidor de stock**: una barra horizontal con marcas de mínimo, objetivo y máximo, y la posición del stock disponible coloreada según su estado. Aparece en Stock, Umbrales y POS, así que la regla "¿cómo estoy respecto a mis umbrales?" se lee igual en todo el sistema.

## Movimiento (MOTION 1)

- Estados hover/pressed (`scale(0.97)` al presionar, 120ms).
- Modal y toasts: entrada con opacity + scale/translate en 180–220ms y `cubic-bezier(0.23, 1, 0.32, 1)`; salida más rápida.
- Nada en bucle infinito. Escanear en el POS no tiene animación (es una acción que se repite cientos de veces al día).
- Con `prefers-reduced-motion` solo quedan fundidos de opacidad.
