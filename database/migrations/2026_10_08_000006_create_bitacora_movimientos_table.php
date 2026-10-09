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
        Schema::create('bitacora_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->cascadeOnUpdate();
            $table->foreignId('variante_id')->constrained('variantes')->cascadeOnUpdate();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipo_movimiento', [
                'venta',
                'ingreso',
                'ajuste',
                'transferencia_salida',
                'transferencia_entrada',
                'devolucion'
            ]);
            $table->integer('cantidad')->comment('Variación neta en unidades');
            $table->integer('stock_fisico_anterior');
            $table->integer('stock_fisico_nuevo');
            $table->string('referencia_tipo')->nullable()->comment('Modelo polimórfico ej: App\\Models\\Venta');
            $table->unsignedBigInteger('referencia_id')->nullable()->comment('ID de la entidad vinculada');
            $table->text('motivo')->nullable();
            $table->timestamps();

            $table->index(['sede_id', 'created_at']);
            $table->index(['variante_id', 'created_at']);
            $table->index(['tipo_movimiento', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitacora_movimientos');
    }
};
