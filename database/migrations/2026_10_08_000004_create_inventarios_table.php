<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('variantes')->cascadeOnDelete();
            
            // Componentes de Stock para Cálculo de Disponibilidad Real
            $table->unsignedInteger('stock_fisico')->default(0)->comment('Stock real físico en tienda/almacén');
            $table->unsignedInteger('stock_reservado')->default(0)->comment('Stock apartado por carritos o pedidos pendientes');
            $table->unsignedInteger('stock_comprometido')->default(0)->comment('Stock vendido pendiente de despacho/entrega');
            
            // Umbrales de Gestión
            $table->unsignedInteger('stock_minimo')->default(5)->comment('Umbral crítico mínimo');
            $table->unsignedInteger('stock_objetivo')->default(15)->comment('Nivel ideal de reposición');
            $table->unsignedInteger('stock_maximo')->default(30)->comment('Límite de capacidad antes de sobrestock');
            
            $table->timestamps();

            // Unicidad por variante y sede
            $table->unique(['sede_id', 'variante_id'], 'inventario_sede_variante_unico');

            // Índices de alta velocidad (<2s de latencia)
            $table->index(['sede_id', 'stock_fisico']);
            $table->index(['variante_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};
