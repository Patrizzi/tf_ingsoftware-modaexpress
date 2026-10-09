<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarUmbralRequest extends FormRequest
{
    /**
     * El acceso por rol lo controla el middleware role:admin,gerente; aquí se verifica
     * que el gerente solo edite umbrales de su propia sede.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $inventario = $this->route('inventario');

        return $user && ($user->isAdmin() || $user->sede_id === $inventario?->sede_id);
    }

    /**
     * Regla estricta: stock_minimo < stock_objetivo <= stock_maximo
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stock_minimo' => ['required', 'integer', 'min:0', 'max:100000'],
            'stock_objetivo' => ['required', 'integer', 'gt:stock_minimo', 'max:100000'],
            'stock_maximo' => ['required', 'integer', 'gte:stock_objetivo', 'max:100000'],
        ];
    }

    /**
     * Mensajes claros para operarios y gerentes de tienda.
     */
    public function messages(): array
    {
        return [
            'stock_minimo.required' => 'Escribe el stock mínimo.',
            'stock_minimo.integer' => 'El mínimo debe ser un número entero.',
            'stock_minimo.min' => 'El mínimo no puede ser negativo.',
            'stock_objetivo.required' => 'Escribe el stock objetivo.',
            'stock_objetivo.integer' => 'El objetivo debe ser un número entero.',
            'stock_objetivo.gt' => 'El objetivo tiene que ser mayor que el mínimo.',
            'stock_maximo.required' => 'Escribe el stock máximo.',
            'stock_maximo.integer' => 'El máximo debe ser un número entero.',
            'stock_maximo.gte' => 'El máximo tiene que ser igual o mayor que el objetivo.',
        ];
    }
}
