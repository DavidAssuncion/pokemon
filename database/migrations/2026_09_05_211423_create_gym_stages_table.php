<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Etapas de cada gimnasio (1-4). vanguardia/retaguardia guardan listas de
     * species_id en columnas JSON (jsonb en pgsql, json/texto en sqlite).
     */
    public function up(): void
    {
        Schema::create('gym_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->onDelete('cascade');
            $table->unsignedTinyInteger('etapa');
            $table->json('vanguardia')->nullable();
            $table->json('retaguardia')->nullable();
            $table->timestamps();

            $table->unique(['gym_id', 'etapa']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_stages');
    }
};
