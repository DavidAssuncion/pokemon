<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Una fila por hueco ocupado de un nido. El stock de bayas se calcula al
     * leer a partir de `planted_at` y `harvest_count`; no se persiste.
     */
    public function up(): void
    {
        Schema::create('nest_plantings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nest_id')->constrained('nests')->onDelete('cascade');
            $table->unsignedSmallInteger('slot_index');
            $table->string('berry_key', 32);
            $table->timestamp('planted_at');
            $table->unsignedSmallInteger('harvest_count')->default(0);
            $table->timestamps();

            $table->unique(['nest_id', 'slot_index']);
            $table->index('berry_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nest_plantings');
    }
};
