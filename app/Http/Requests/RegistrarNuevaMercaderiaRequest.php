<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarNuevaMercaderiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('sku')) {
            $this->merge(['sku' => strtoupper(trim((string) $this->input('sku')))]);
        }
        if ($this->has('codigo_modelo')) {
            $this->merge(['codigo_modelo' => strtoupper(trim((string) $this->input('codigo_modelo')))]);
        }
        if ($this->has('talla')) {
            $this->merge(['talla' => strtoupper(trim((string) $this->input('talla')))]);
        }
    }

    public function rules(): array
    {
        $modoProducto = $this->input('modo_producto', 'nuevo');
        $esExistente = $modoProducto === 'existente';

        return [
            'sede_id' => ['required', 'integer', 'exists:sedes,id'],
            'modo_producto' => ['required', 'string', 'in:nuevo,existente'],

            // Si asocia a un modelo existente
            'producto_id' => $esExistente
                ? ['required', 'integer', 'exists:productos,id']
                : ['nullable'],

            // Si crea un nuevo modelo de prenda
            'codigo_modelo' => $esExistente
                ? ['nullable']
                : ['required', 'string', 'max:50', 'unique:productos,codigo_modelo'],
            'nombre_producto' => $esExistente
                ? ['nullable']
                : ['required', 'string', 'max:150'],
            'categoria' => $esExistente
                ? ['nullable']
                : ['required', 'string', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:500'],

            // Datos de la variante (SKU único)
            'sku' => ['required', 'string', 'max:60', 'unique:variantes,sku'],
            'codigo_barra' => ['required', 'string', 'max:60', 'unique:variantes,codigo_barra'],
            'talla' => [
                'required',
                'string',
                'max:20',
                $esExistente
                    ? Rule::unique('variantes', 'talla')->where(fn ($q) => $q
                        ->where('producto_id', (int) $this->input('producto_id'))
                        ->where('color', trim((string) $this->input('color'))))
                    : null,
            ],
            'color' => ['required', 'string', 'max:40'],
            'precio' => ['required', 'numeric', 'min:0.10', 'max:99999.99'],

            // Stock inicial y umbrales para la sede
            'cantidad' => ['required', 'integer', 'min:0', 'max:99999'],
            'stock_minimo' => ['required', 'integer', 'min:0'],
            'stock_objetivo' => ['required', 'integer', 'gt:stock_minimo'],
            'stock_maximo' => ['required', 'integer', 'gte:stock_objetivo'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'producto_id.required' => 'Selecciona el modelo de prenda al que pertenece esta variante.',
            'codigo_modelo.required' => 'Ingresa el código del modelo (ej. POL-BAS).',
            'codigo_modelo.unique' => 'Ya existe un modelo con ese código. Elige "Modelo existente" o usa otro código.',
            'nombre_producto.required' => 'Ingresa el nombre de la prenda.',
            'categoria.required' => 'Indica la categoría de la prenda (ej. Polos, Camisas, Pantalones).',
            'sku.required' => 'El código SKU es obligatorio.',
            'sku.unique' => 'Este SKU ya está registrado en el catálogo. Usa la pestaña "Mercadería existente" para sumarle stock.',
            'codigo_barra.required' => 'El código de barras es obligatorio.',
            'codigo_barra.unique' => 'Este código de barras ya pertenece a otra prenda del catálogo.',
            'talla.required' => 'Indica la talla de la prenda.',
            'talla.unique' => 'Ya existe una variante con esa misma talla y color para este modelo.',
            'color.required' => 'Indica el color de la prenda.',
            'precio.required' => 'Indica el precio de venta (incluye IGV).',
            'precio.min' => 'El precio debe ser mayor a S/ 0.00.',
            'cantidad.required' => 'Indica la cantidad inicial a ingresar.',
            'cantidad.min' => 'La cantidad inicial no puede ser negativa.',
            'stock_objetivo.gt' => 'El stock objetivo debe ser mayor al stock mínimo.',
            'stock_maximo.gte' => 'El stock máximo debe ser mayor o igual al stock objetivo.',
        ];
    }
}
