<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Progreso de Sendas por cuenta: una fila por usuario y tipo.
     * La experiencia es la misma que reciben los combatientes/exploradores
     * (una sola vez por evento), sin generar caramelos.
     */
    public function up(): void
    {
        Schema::create('player_type_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('type_id');
            $table->unsignedBigInteger('exp')->default(0);
            $table->unsignedTinyInteger('level')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'type_id']);
            $table->index(['user_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_type_paths');
    }
};
