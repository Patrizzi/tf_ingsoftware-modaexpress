<?php

namespace Database\Seeders;

use App\Models\Alerta;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\User;
use App\Models\Variante;
use App\Services\AlertaService;
use App\Services\InventarioService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ModaExpressSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear las 5 tiendas físicas y la 1 plataforma online de ModaExpress
        $sedes = [
            ['codigo' => 'TIENDA-01', 'nombre' => 'ModaExpress - Miraflores (Central)', 'tipo' => 'fisica', 'direccion' => 'Av. Larco 450, Lima', 'telefono' => '01-4458921'],
            ['codigo' => 'TIENDA-02', 'nombre' => 'ModaExpress - San Isidro', 'tipo' => 'fisica', 'direccion' => 'Av. Conquistadores 780, Lima', 'telefono' => '01-2219430'],
            ['codigo' => 'TIENDA-03', 'nombre' => 'ModaExpress - Jockey Plaza', 'tipo' => 'fisica', 'direccion' => 'C.C. Jockey Plaza Nivel 2, Surco', 'telefono' => '01-4375510'],
            ['codigo' => 'TIENDA-04', 'nombre' => 'ModaExpress - San Miguel', 'tipo' => 'fisica', 'direccion' => 'Av. La Marina 2000, San Miguel', 'telefono' => '01-5661122'],
            ['codigo' => 'TIENDA-05', 'nombre' => 'ModaExpress - Plaza Norte', 'tipo' => 'fisica', 'direccion' => 'Av. Tomás Valle cruce Panamericana Norte', 'telefono' => '01-5348899'],
            ['codigo' => 'ONLINE-01', 'nombre' => 'ModaExpress - Tienda Online (Ecommerce)', 'tipo' => 'online', 'direccion' => 'Almacén Central Hub Web', 'telefono' => '01-800MODA'],
        ];

        $sedeModels = [];
        foreach ($sedes as $s) {
            $sedeModels[] = Sede::firstOrCreate(['codigo' => $s['codigo']], $s);
        }

        // 2. Usuarios de demostración, uno por rol (contraseña: password123)
        $usuarios = [
            ['email' => 'admin@modaexpress.com', 'name' => 'Administrador', 'rol' => 'admin', 'sede_id' => $sedeModels[0]->id],
            ['email' => 'gerente@modaexpress.com', 'name' => 'Gerente Miraflores', 'rol' => 'gerente', 'sede_id' => $sedeModels[0]->id],
            ['email' => 'cajero@modaexpress.com', 'name' => 'Caja Miraflores', 'rol' => 'vendedor', 'sede_id' => $sedeModels[0]->id],
        ];

        foreach ($usuarios as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                $u + ['password' => Hash::make('password123')]
            );
        }

        // 3. Crear Catálogo de Prendas y Variantes (SKU único sin mezclar tallas ni colores)
        $catalogo = [
            [
                'producto' => ['codigo_modelo' => 'POL-PIMA', 'nombre' => 'Polo Cuello Redondo Algodón Pima', 'categoria' => 'Polos', 'precio_base' => 69.90],
                'variantes' => [
                    ['talla' => 'S', 'color' => 'Negro', 'sku' => 'POL-PIM-NEG-S', 'codigo_barra' => '775001001001', 'precio' => 69.90],
                    ['talla' => 'M', 'color' => 'Negro', 'sku' => 'POL-PIM-NEG-M', 'codigo_barra' => '775001001002', 'precio' => 69.90],
                    ['talla' => 'L', 'color' => 'Negro', 'sku' => 'POL-PIM-NEG-L', 'codigo_barra' => '775001001003', 'precio' => 69.90],
                    ['talla' => 'M', 'color' => 'Blanco', 'sku' => 'POL-PIM-BLA-M', 'codigo_barra' => '775001001004', 'precio' => 69.90],
                ]
            ],
            [
                'producto' => ['codigo_modelo' => 'CAM-OXF', 'nombre' => 'Camisa Manga Larga Oxford Slim Fit', 'categoria' => 'Camisas', 'precio_base' => 129.00],
                'variantes' => [
                    ['talla' => 'M', 'color' => 'Celeste', 'sku' => 'CAM-OXF-CEL-M', 'codigo_barra' => '775002002001', 'precio' => 129.00],
                    ['talla' => 'L', 'color' => 'Celeste', 'sku' => 'CAM-OXF-CEL-L', 'codigo_barra' => '775002002002', 'precio' => 129.00],
                    ['talla' => 'M', 'color' => 'Blanco', 'sku' => 'CAM-OXF-BLA-M', 'codigo_barra' => '775002002003', 'precio' => 129.00],
                ]
            ],
            [
                'producto' => ['codigo_modelo' => 'JEA-SLM', 'nombre' => 'Pantalón Jean Denim Strech', 'categoria' => 'Pantalones', 'precio_base' => 159.00],
                'variantes' => [
                    ['talla' => '30', 'color' => 'Azul Medio', 'sku' => 'JEA-SLM-AZM-30', 'codigo_barra' => '775003003001', 'precio' => 159.00],
                    ['talla' => '32', 'color' => 'Azul Medio', 'sku' => 'JEA-SLM-AZM-32', 'codigo_barra' => '775003003002', 'precio' => 159.00],
                    ['talla' => '34', 'color' => 'Azul Oscuro', 'sku' => 'JEA-SLM-AZO-34', 'codigo_barra' => '775003003003', 'precio' => 159.00],
                ]
            ]
        ];

        $variantesModel = [];

        foreach ($catalogo as $cat) {
            $prod = Producto::firstOrCreate(
                ['codigo_modelo' => $cat['producto']['codigo_modelo']],
                $cat['producto']
            );

            foreach ($cat['variantes'] as $vData) {
                $vData['producto_id'] = $prod->id;
                $variantesModel[] = Variante::firstOrCreate(
                    ['sku' => $vData['sku']],
                    $vData
                );
            }
        }

        // 4. Poblar inventarios simulando situaciones reales:
        // - En TIENDA-01 (Miraflores): Stock crítico en Polo Negro M (3 físicos, mínimo 5, obj 15, max 30) -> Déficit
        // - En TIENDA-02 (San Isidro): Excedente/sobrestock en Polo Negro M (45 físicos, mínimo 5, obj 15, max 30) -> Sugerencia de transferencia
        // - En TIENDA-03 (Jockey Plaza): Excedente en Camisa Celeste M (50 físicos, max 30)
        $alertaService = app(AlertaService::class);

        foreach ($sedeModels as $sede) {
            foreach ($variantesModel as $index => $variante) {
                
                // Configurar escenarios de prueba para demostrar el módulo de alertas y redistribución
                if ($sede->codigo === 'TIENDA-01' && $variante->sku === 'POL-PIM-NEG-M') {
                    // Estado Crítico en Miraflores
                    $fisico = 3;
                    $reservado = 1;
                    $comprometido = 0; // Disponible: 2 <= Minimo (5)
                    $min = 5;
                    $obj = 15;
                    $max = 30;
                } elseif ($sede->codigo === 'TIENDA-02' && $variante->sku === 'POL-PIM-NEG-M') {
                    // Sobrestock masivo en San Isidro (Excedente listo para transferir a Miraflores)
                    $fisico = 45;
                    $reservado = 0;
                    $comprometido = 0; // Disponible: 45 > Maximo (30)
                    $min = 5;
                    $obj = 15;
                    $max = 30;
                } elseif ($sede->codigo === 'TIENDA-03' && $variante->sku === 'POL-PIM-NEG-M') {
                    // Otra tienda con excedente
                    $fisico = 38;
                    $reservado = 2;
                    $comprometido = 1; // Disponible: 35 > Maximo (30)
                    $min = 5;
                    $obj = 15;
                    $max = 30;
                } elseif ($sede->codigo === 'TIENDA-01' && $variante->sku === 'CAM-OXF-CEL-M') {
                    // Estado Preventivo en Miraflores (disponible <= objetivo)
                    $fisico = 9;
                    $reservado = 1;
                    $comprometido = 0; // Disponible: 8 (min 5 < 8 <= obj 15)
                    $min = 5;
                    $obj = 15;
                    $max = 35;
                } else {
                    // Stock normal balanceado
                    $fisico = rand(16, 25);
                    $reservado = rand(0, 2);
                    $comprometido = rand(0, 1);
                    $min = 5;
                    $obj = 15;
                    $max = 35;
                }

                $inventario = Inventario::updateOrCreate(
                    [
                        'sede_id' => $sede->id,
                        'variante_id' => $variante->id,
                    ],
                    [
                        'stock_fisico' => $fisico,
                        'stock_reservado' => $reservado,
                        'stock_comprometido' => $comprometido,
                        'stock_minimo' => $min,
                        'stock_objetivo' => $obj,
                        'stock_maximo' => $max,
                    ]
                );

                // Evaluar alertas automáticamente
                $alertaService->evaluarInventario($inventario);
            }
        }
    }
}
