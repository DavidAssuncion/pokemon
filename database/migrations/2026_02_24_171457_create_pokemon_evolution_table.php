<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pokemon_evolution', function (Blueprint $table) {
            $table->id();
            $table->integer('evolution_chain_id')->nullable();
            $table->foreignId('evolved_species_id')->constrained('pokemon')->onDelete('cascade');
            $table->integer('evolves_from_species_id')->nullable();
            $table->integer('minimum_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pokemon_evolution');
    }
};
