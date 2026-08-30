<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * D0: peligro del hábitat 1–5 (bosque tranquilo = 1, cueva de dragones = 5).
     * Estrellas UI = valor. Default 1 (zona tranquila) para los hábitats sin dato.
     */
    public function up(): void
    {
        Schema::table('habitats', function (Blueprint $table) {
            $table->unsignedSmallInteger('peligro')->nullable()->default(1)->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('habitats', function (Blueprint $table) {
            $table->dropColumn('peligro');
        });
    }
};
