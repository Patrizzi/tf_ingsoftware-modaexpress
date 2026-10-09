<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida una venta del POS o del e-commerce.
 * El canal lo fija el controlador y el precio se toma del catálogo; el cliente solo envía qué y cuánto.
 */
class RegistrarVentaRequest extends FormRequest
{
    public const METODOS_PAGO = ['efectivo', 'tarjeta', 'yape_plin', 'pasarela'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sede_id' => ['required', 'integer', 'exists:sedes,id'],
            'metodo_pago' => ['required', 'string', 'in:'.implode(',', self::METODOS_PAGO)],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.variante_id' => ['required', 'integer', 'exists:variantes,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'sede_id.required' => 'Indica la tienda que emite la venta.',
            'sede_id.exists' => 'La tienda indicada no existe.',
            'metodo_pago.required' => 'Elige cómo paga el cliente.',
            'metodo_pago.in' => 'El método de pago no es válido.',
            'items.required' => 'Agrega al menos un producto a la venta.',
            'items.min' => 'Agrega al menos un producto a la venta.',
            'items.*.variante_id.exists' => 'Uno de los productos ya no existe en el catálogo.',
            'items.*.cantidad.min' => 'Cada producto debe tener al menos 1 unidad.',
        ];
    }
}
