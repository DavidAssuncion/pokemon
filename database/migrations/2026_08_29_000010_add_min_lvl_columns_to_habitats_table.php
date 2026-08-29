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
        // Solo columnas: la lógica de nivel mínimo por nivel 1/2/3 la añade la fase C.
        Schema::table('habitats', function (Blueprint $table) {
            $table->unsignedInteger('min_lvl_1')->nullable()->after('name');
            $table->unsignedInteger('min_lvl_2')->nullable()->after('min_lvl_1');
            $table->unsignedInteger('min_lvl_3')->nullable()->after('min_lvl_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('habitats', function (Blueprint $table) {
            $table->dropColumn(['min_lvl_1', 'min_lvl_2', 'min_lvl_3']);
        });
    }
};
