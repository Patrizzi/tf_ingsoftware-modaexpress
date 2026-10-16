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
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('tipo_comprobante', 20)->default('boleta')->after('codigo_comprobante')->comment('boleta o factura');
            $table->string('cliente_documento', 20)->nullable()->after('metodo_pago')->comment('DNI (8 dígitos) o RUC (11 dígitos)');
            $table->string('cliente_nombre', 180)->nullable()->after('cliente_documento')->comment('Nombre completo o Razón Social');
            $table->string('cliente_direccion', 255)->nullable()->after('cliente_nombre')->comment('Dirección fiscal para factura');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_comprobante',
                'cliente_documento',
                'cliente_nombre',
                'cliente_direccion',
            ]);
        });
    }
};
