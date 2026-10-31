<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Nodos de las Sendas: un movimiento por tipo. El coste no vive aquí:
     * se resuelve con las constantes de App\Support\SendaCostes a partir del
     * nivel requerido de la Senda.
     */
    public function up(): void
    {
        Schema::create('type_path_nodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('type_id');
            $table->foreignId('move_id')->constrained('moves')->cascadeOnDelete();
            $table->string('category', 16);
            $table->unsignedTinyInteger('required_level')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['type_id', 'move_id']);
            $table->index(['type_id', 'category', 'required_level', 'sort_order'], 'type_path_nodes_type_category_level_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('type_path_nodes');
    }
};
