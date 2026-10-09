<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica a la plataforma e-commerce mediante la cabecera X-Api-Key.
 * La clave vive en ECOMMERCE_API_KEY (.env); sin clave configurada el endpoint queda cerrado.
 */
class EcommerceApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $esperada = (string) config('services.ecommerce.key');
        $recibida = (string) $request->header('X-Api-Key', '');

        if ($esperada === '' || ! hash_equals($esperada, $recibida)) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Clave de API inválida o ausente.',
            ], 401);
        }

        return $next($request);
    }
}
