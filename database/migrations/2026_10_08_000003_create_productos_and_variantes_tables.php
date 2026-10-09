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
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_modelo', 50)->unique()->comment('Código de modelo/estilo padre');
            $table->string('nombre', 150);
            $table->string('categoria', 80)->index();
            $table->text('descripcion')->nullable();
            $table->decimal('precio_base', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('sku', 60)->unique()->comment('SKU único normalizado por talla y color');
            $table->string('codigo_barra', 60)->unique()->index()->comment('Código de barras físico para lectura rápida por escáner');
            $table->string('talla', 20)->comment('Talla específica ej: S, M, L, 32, 34');
            $table->string('color', 40)->comment('Color específico ej: Negro, Blanco, Azul');
            $table->decimal('precio', 10, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['producto_id', 'talla', 'color'], 'variante_unica_prod_talla_color');
            $table->index(['activo', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variantes');
        Schema::dropIfExists('productos');
    }
};
