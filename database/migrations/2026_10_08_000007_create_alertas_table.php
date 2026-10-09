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
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('variantes')->cascadeOnDelete();
            $table->enum('tipo', ['critico', 'preventivo', 'sobrestock'])->comment('Nivel de urgencia de la alerta');
            $table->string('estado', 20)->default('activa')->comment('activa, resuelta, descartada');
            $table->integer('stock_disponible_calculado')->comment('Físico - Reservado - Comprometido al momento de alertar');
            $table->integer('stock_minimo_umbral');
            $table->integer('stock_objetivo_umbral');
            $table->integer('stock_maximo_umbral');
            $table->text('mensaje');
            $table->timestamp('fecha_resuelta')->nullable();
            $table->timestamps();

            // Optimización de consultas del panel (< 2 segundos) y prevención de duplicados
            $table->index(['sede_id', 'estado', 'tipo']);
            $table->index(['variante_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};
