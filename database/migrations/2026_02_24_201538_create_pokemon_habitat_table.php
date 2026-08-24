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
        Schema::create('pokemon_habitat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pokemon_id')->constrained('pokemon')->onDelete('cascade');
            $table->foreignId('habitat_id')->constrained('habitats')->onDelete('cascade');
            $table->integer('level')->min(1)->max(3);
            $table->unique(['pokemon_id', 'habitat_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pokemon_habitat');
    }
};
