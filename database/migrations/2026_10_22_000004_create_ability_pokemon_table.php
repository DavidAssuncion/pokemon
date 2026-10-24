<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('ability_pokemon', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pokemon_id');
            $table->unsignedBigInteger('ability_id');
            $table->tinyInteger('slot')->unsigned()->default(1);
            $table->string('source')->default('poke_habilidades');
            $table->timestamps();

            $table->unique(['pokemon_id', 'slot']);
            $table->foreign('pokemon_id')->references('id')->on('pokemon')->onDelete('cascade');
            $table->foreign('ability_id')->references('id')->on('abilities')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ability_pokemon');
    }
};
