<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exploraciones_activas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipo_id');
            $table->json('eventos')->nullable();
            $table->timestamp('inicio_exploracion')->nullable();
            $table->timestamp('llegada_destino')->nullable();
            $table->timestamp('regreso')->nullable();
            $table->timestamps();

            $table->foreign('equipo_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exploraciones_activas');
    }
};
