<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Evento global de avistamientos: una fila por ventana de 3 días. La fila
     * se inserta una sola vez (unique por `window_index`) y nunca se actualiza;
     * `evolution_chain_id` es una columna de `pokemon`, no una tabla, así que
     * no lleva clave foránea.
     */
    public function up(): void
    {
        Schema::create('avistamiento_eventos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('window_index');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->foreignId('habitat_id')->constrained('habitats')->restrictOnDelete();
            $table->unsignedBigInteger('evolution_chain_id');
            $table->decimal('factor', 6, 2)->default(5.00);
            $table->timestamps();

            $table->unique('window_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avistamiento_eventos');
    }
};
