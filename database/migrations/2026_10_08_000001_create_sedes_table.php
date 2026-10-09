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
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique()->comment('Identificador único legible ej: TIENDA-01, ONLINE-01');
            $table->string('nombre', 100);
            $table->enum('tipo', ['fisica', 'online'])->default('fisica');
            $table->string('direccion')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index('tipo');
            $table->index('activa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sedes');
    }
};
