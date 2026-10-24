<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Relación Pokémon (especie) ↔ movimientos del catálogo.
     * `slot` conserva el orden de la asignación.
     */
    public function up(): void
    {
        Schema::create('pokemon_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pokemon_id')->constrained('pokemon')->cascadeOnDelete();
            $table->foreignId('move_id')->constrained('moves')->cascadeOnDelete();
            $table->unsignedSmallInteger('slot')->default(1);
            $table->string('source')->default('custom');
            $table->timestamps();

            $table->unique(['pokemon_id', 'slot']);
            $table->unique(['pokemon_id', 'move_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pokemon_moves');
    }
};
