<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Granjas por jugador y hábitat. Cada granja contiene nidos (IV/tiempo de
     * huevo), refugios (aparición/captura) y complementos EV por familia.
     */
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('habitat_id')->constrained('habitats')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'habitat_id']);
        });

        Schema::create('nests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->onDelete('cascade');
            $table->unsignedBigInteger('evolution_chain_id');
            $table->unsignedTinyInteger('level')->default(0);
            $table->timestamps();

            $table->unique(['farm_id', 'evolution_chain_id']);
            $table->index('evolution_chain_id');
        });

        Schema::create('shelters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->onDelete('cascade');
            $table->unsignedBigInteger('evolution_chain_id');
            $table->unsignedTinyInteger('level')->default(0);
            $table->boolean('unhealthy_conditions')->default(false);
            $table->timestamps();

            $table->unique(['farm_id', 'evolution_chain_id']);
            $table->index('evolution_chain_id');
        });

        Schema::create('complements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shelter_id')->constrained('shelters')->onDelete('cascade');
            $table->unsignedTinyInteger('stat');
            $table->unsignedTinyInteger('level')->default(0);
            $table->timestamps();

            $table->unique(['shelter_id', 'stat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complements');
        Schema::dropIfExists('shelters');
        Schema::dropIfExists('nests');
        Schema::dropIfExists('farms');
    }
};
