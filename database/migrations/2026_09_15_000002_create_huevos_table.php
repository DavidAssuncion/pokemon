<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Huevos del jugador. capture_rate es un snapshot del pokémon base en el
     * momento de crear el huevo (la gestación/eclosión depende de él).
     * es_shiny + ivs se fijan al crear; eclosionado_en se marca al eclosionar.
     */
    public function up(): void
    {
        Schema::create('huevos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pokemon_id')->constrained('pokemon')->cascadeOnDelete();
            $table->unsignedSmallInteger('capture_rate')->default(0);
            $table->boolean('es_shiny')->default(false);
            $table->json('ivs')->nullable();
            $table->timestamp('eclosionado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huevos');
    }
};
