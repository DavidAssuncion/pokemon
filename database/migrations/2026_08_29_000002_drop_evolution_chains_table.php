<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Elimina la tabla evolution_chains: su única columna útil (data, json)
     * nunca se leyó; la agrupación de familias vive en pokemon.evolution_chain_id.
     */
    public function up(): void
    {
        Schema::dropIfExists('evolution_chains');
    }

    /**
     * Reverse the migrations: recrea el esquema original.
     */
    public function down(): void
    {
        Schema::create('evolution_chains', function (Blueprint $table) {
            $table->id();
            $table->json('data')->nullable();
        });
    }
};
