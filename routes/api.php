<?php

use App\Http\Controllers\Api\VentaOnlineController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Canal E-commerce
|--------------------------------------------------------------------------
| Requiere la cabecera X-Api-Key con el valor de ECOMMERCE_API_KEY.
*/

Route::prefix('v1')->middleware(['ecommerce.key', 'throttle:120,1'])->group(function () {
    Route::post('/ventas/online', [VentaOnlineController::class, 'store'])->name('api.ventas.online');
});
