<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Crea la tabla unificada `favoritos` y elimina el diseño legacy
     * (habitat_favoritos y la columna reclutados.favorito) que reemplaza.
     */
    public function up(): void
    {
        // Limpieza legacy (idempotente): tabla antigua y boolean en reclutados.
        Schema::dropIfExists('habitat_favoritos');

        if (Schema::hasColumn('reclutados', 'favorito')) {
            Schema::table('reclutados', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'favorito']);
                $table->dropColumn('favorito');
            });
        }

        // Tabla unificada de favoritos.
        Schema::create('favoritos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('reclutado_id');
            $table->unsignedBigInteger('habitat_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reclutado_id')->references('id')->on('reclutados')->onDelete('cascade');
            $table->foreign('habitat_id')->references('id')->on('habitats')->onDelete('cascade');
            $table->unique(['user_id', 'reclutado_id', 'habitat_id']);
            $table->index(['user_id', 'habitat_id']);
            $table->index(['user_id', 'reclutado_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favoritos');

        // Restaura el diseño legacy para un rollback simétrico.
        if (! Schema::hasColumn('reclutados', 'favorito')) {
            Schema::table('reclutados', function (Blueprint $table) {
                $table->boolean('favorito')->default(false)->after('es_shiny');
                $table->index(['user_id', 'favorito']);
            });
        }
    }
};
