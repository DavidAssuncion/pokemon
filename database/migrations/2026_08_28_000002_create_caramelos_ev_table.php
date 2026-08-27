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
        Schema::create('caramelos_ev', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('stat');
            $table->unsignedBigInteger('cantidad')->default(0);
            $table->timestamps();
            $table->unique('stat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caramelos_ev');
    }
};
