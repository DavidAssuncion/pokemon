<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Elimina la FK de caramelos hacia evolution_chains (causa del bug 23503:
     * cadenas huérfanas sin fila rompían el insert de caramelos de familia).
     * La columna evolution_chain_id se conserva (agrupación por columna).
     */
    public function up(): void
    {
        Schema::table('caramelos', function (Blueprint $table) {
            $table->dropForeign('caramelos_evolution_chain_id_foreign');
        });
    }

    /**
     * Reverse the migrations: recrea la FK asumiendo que el rollback anterior
     * ya recreó la tabla evolution_chains (orden inverso de migraciones).
     */
    public function down(): void
    {
        Schema::table('caramelos', function (Blueprint $table) {
            $table->foreign('evolution_chain_id')->references('id')->on('evolution_chains')->onDelete('cascade');
        });
    }
};
