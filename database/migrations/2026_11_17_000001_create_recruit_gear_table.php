<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Equipamiento de piezas forjadas por reclutado (3 slots).
     *
     * `reclutados.obj_equipados` queda intacto: modela el objeto de combate
     * robable/intercambiable, no la pieza forjada (no robable).
     */
    public function up(): void
    {
        Schema::create('recruit_gear', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reclutado_id')->constrained('reclutados')->cascadeOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->string('item_key', 64);
            $table->timestamps();

            $table->unique(['reclutado_id', 'slot']);
            $table->unique(['reclutado_id', 'item_key']);
            $table->index('item_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruit_gear');
    }
};
