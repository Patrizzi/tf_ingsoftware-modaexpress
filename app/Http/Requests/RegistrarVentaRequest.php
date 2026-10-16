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
    public const TIPOS_COMPROBANTE = ['boleta', 'factura'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $esFactura = $this->input('tipo_comprobante') === 'factura';

        return [
            'sede_id' => ['required', 'integer', 'exists:sedes,id'],
            'metodo_pago' => ['required', 'string', 'in:'.implode(',', self::METODOS_PAGO)],
            'tipo_comprobante' => ['nullable', 'string', 'in:'.implode(',', self::TIPOS_COMPROBANTE)],
            'cliente_documento' => $esFactura
                ? ['required', 'string', 'regex:/^\d{11}$/']
                : ['nullable', 'string', 'max:20'],
            'cliente_nombre' => $esFactura
                ? ['required', 'string', 'max:180']
                : ['nullable', 'string', 'max:180'],
            'cliente_direccion' => ['nullable', 'string', 'max:255'],
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
            'tipo_comprobante.in' => 'El tipo de comprobante debe ser boleta o factura.',
            'cliente_documento.required' => 'Para emitir factura es obligatorio ingresar el RUC del cliente.',
            'cliente_documento.regex' => 'El RUC para factura debe tener exactamente 11 dígitos numéricos.',
            'cliente_nombre.required' => 'Para emitir factura es obligatorio ingresar la razón social del cliente.',
            'items.required' => 'Agrega al menos un producto a la venta.',
            'items.min' => 'Agrega al menos un producto a la venta.',
            'items.*.variante_id.exists' => 'Uno de los productos ya no existe en el catálogo.',
            'items.*.cantidad.min' => 'Cada producto debe tener al menos 1 unidad.',
        ];
    }
}
