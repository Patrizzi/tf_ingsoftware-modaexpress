<?php

use App\Http\Controllers\AlertaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - ModaExpress Inventario
|--------------------------------------------------------------------------
| Permisos:
|   vendedor  -> POS, stock y alertas de su tienda
|   gerente   -> lo anterior + umbrales y movimientos de su tienda
|   admin     -> todo, en todas las sedes
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', function () {
        return auth()->user()->isVendedor()
            ? redirect()->route('ventas.pos')
            : redirect()->route('stock.index');
    })->name('home');

    // Consulta (todos los roles)
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
    Route::get('/alertas/{varianteId}/sugerencias', [AlertaController::class, 'sugerenciasTransferencia'])
        ->whereNumber('varianteId')->name('alertas.sugerencias');

    // Punto de venta (todos los roles)
    Route::get('/ventas/pos', [VentaController::class, 'pos'])->name('ventas.pos');
    Route::get('/ventas/buscar-codigo', [VentaController::class, 'buscarCodigo'])->name('ventas.buscar_codigo');
    Route::post('/ventas', [VentaController::class, 'store'])->name('ventas.store');
    Route::get('/ventas/{venta}/comprobante', [VentaController::class, 'comprobante'])
        ->whereNumber('venta')->name('ventas.comprobante');

    // Gestión (admin y gerente)
    Route::middleware('role:admin,gerente')->group(function () {
        Route::get('/inventario/ingreso', [InventarioController::class, 'ingreso'])->name('inventario.ingreso');
        Route::post('/inventario/ingreso/existente', [InventarioController::class, 'ingresarExistente'])->name('inventario.ingreso.existente');
        Route::post('/inventario/ingreso/nueva', [InventarioController::class, 'registrarNueva'])->name('inventario.ingreso.nueva');
        Route::get('/inventario/umbrales', [InventarioController::class, 'umbrales'])->name('inventario.umbrales');
        Route::put('/inventario/{inventario}/umbral', [InventarioController::class, 'actualizarUmbral'])->name('inventario.umbral.update');
        Route::get('/movimientos', [MovimientoController::class, 'index'])->name('movimientos.index');
    });
});
