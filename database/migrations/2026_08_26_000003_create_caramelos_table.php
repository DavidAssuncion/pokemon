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
        Schema::create('caramelos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evolution_chain_id')->constrained('evolution_chains')->onDelete('cascade');
            $table->unsignedInteger('cantidad')->default(0);
            $table->timestamps();
            $table->unique('evolution_chain_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caramelos');
    }
};
