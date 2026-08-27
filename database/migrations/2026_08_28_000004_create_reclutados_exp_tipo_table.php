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
        Schema::create('reclutados_exp_tipo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reclutado_id')->constrained('reclutados')->onDelete('cascade');
            $table->string('tipo');
            $table->unsignedBigInteger('cantidad')->default(0);
            $table->timestamps();
            $table->unique(['reclutado_id', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reclutados_exp_tipo');
    }
};
