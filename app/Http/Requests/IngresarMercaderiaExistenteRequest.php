<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IngresarMercaderiaExistenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sede_id' => ['required', 'integer', 'exists:sedes,id'],
            'variante_id' => ['required', 'integer', 'exists:variantes,id'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:99999'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'sede_id.required' => 'Selecciona la sede donde ingresará la mercadería.',
            'variante_id.required' => 'Selecciona una prenda del catálogo para ingresar stock.',
            'variante_id.exists' => 'La prenda seleccionada no existe en el catálogo.',
            'cantidad.required' => 'Ingresa la cantidad de unidades recibidas.',
            'cantidad.min' => 'La cantidad a ingresar debe ser de al menos 1 unidad.',
            'cantidad.max' => 'La cantidad supera el límite permitido por operación.',
        ];
    }
}
