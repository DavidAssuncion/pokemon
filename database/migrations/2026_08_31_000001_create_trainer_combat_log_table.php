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
        Schema::create('trainer_combat_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('habitat_id')->constrained('habitats')->onDelete('cascade');
            $table->unsignedTinyInteger('level');
            $table->unsignedTinyInteger('trainer_index');
            $table->boolean('won')->default(false);
            $table->date('fought_at');
            $table->timestamps();

            $table->unique(['user_id', 'habitat_id', 'level', 'trainer_index', 'fought_at'], 'trainer_log_encounter_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_combat_log');
    }
};
