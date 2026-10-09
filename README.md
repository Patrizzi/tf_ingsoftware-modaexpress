# ModaExpress — Sistema de Gestión de Inventarios en Tiempo Real

> Trabajo Final del Curso: Ingeniería del Software — SENATI  
> Solución integral para PYME de retail con 5 tiendas físicas y canal de comercio electrónico.

---

## 1. Características Principales

* **Control de Stock Centralizado:** Cálculo de disponibilidad real ($\text{Físico} - \text{Reservado} - \text{Comprometido}$) por sede en tiempo real.
* **Transacciones Atómicas y Bloqueo Concurrente:** Descuento inmediato de inventario con `DB::transaction()` y `lockForUpdate()`, previniendo sobreventas y stock negativo.
* **Módulo de Alertas Automatizado:** Alertas críticas ($\le \text{Mínimo}$), preventivas ($\le \text{Objetivo}$) y de sobrestock ($> \text{Máximo}$).
* **Motor de Redistribución:** Sugerencias automáticas de transferencia entre tiendas cuando una sede tiene déficit y otra tiene excedente.
* **Punto de Venta (POS):** Optimizado para escaneo continuo con lector de código de barras o SKU, cálculo exacto de IGV (18%) y atajo rápido `F2`.
* **Bitácora Inmutable:** Registro de auditoría de cada movimiento (ventas, transferencias, ajustes) con usuario y motivo.
* **Canal E-commerce:** Endpoint RESTful `/api/v1/ventas/online` protegido por clave de API (`X-Api-Key`).
* **Diseño e Interfaz:** Desarrollado bajo directrices de *Anti-Slop UI*, *Apple Design* y *Emil Kowalski Design Engineering*:
  * Paleta aprobada en `DESIGN.md` (azul de marca `#2641f8`, fondo `#f3f3f4`, acentos semánticos).
  * Soporte nativo para modo claro y oscuro persistente.
  * Medidor visual de stock (Gauge) como motivo de identidad.

---

## 2. Usuarios de Demostración

| Rol | Correo | Contraseña | Acceso predeterminado |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin@modaexpress.com` | `password123` | Control total, todas las sedes |
| **Gerente de Tienda** | `gerente@modaexpress.com` | `password123` | Stock, Alertas, Umbrales, Movimientos y POS de su tienda |
| **Vendedor / Cajero** | `cajero@modaexpress.com` | `password123` | Punto de Venta (POS), Stock y Alertas de su tienda |

---

## 3. Instalación y Puesta en Marcha

### Requisitos
* PHP 8.2 o superior con extensiones `pdo_sqlite` y/o `pdo_mysql`.
* Composer 2.x

### Pasos rápidos
```bash
# 1. Instalar dependencias de PHP
composer install

# 2. Configurar el entorno (ya preconfigurado en .env con SQLite listo para uso)
php artisan key:generate

# 3. Ejecutar migraciones y poblar datos de prueba
php artisan migrate --seed

# 4. Iniciar el servidor local
php artisan serve
```
El sistema estará accesible en `http://localhost:8000`.

---

## 4. Ejecución de Pruebas Automatizadas

El sistema cuenta con una suite completa de 14 pruebas que validan el 100% de la lógica de negocio:

```bash
php artisan test
```

---

## 5. Documentación del Trabajo Final
La memoria académica con el análisis del caso, las respuestas a las 5 preguntas guías, los diagramas UML (Casos de Uso, Clases, Actividades), el flujo BPMN y la matriz MoSCoW se encuentra en:
* [`ENTREGABLE_TRABAJO_FINAL.md`](ENTREGABLE_TRABAJO_FINAL.md)
