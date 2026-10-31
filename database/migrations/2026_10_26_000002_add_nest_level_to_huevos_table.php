<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Snapshot del nivel de nido con el que se puso el huevo: fija el rango de
     * IVs y el tiempo de gestación/eclosión aunque el nido suba después.
     */
    public function up(): void
    {
        Schema::table('huevos', function (Blueprint $table) {
            $table->unsignedTinyInteger('nido_nivel')->default(0)->after('hatch');
        });
    }

    public function down(): void
    {
        Schema::table('huevos', function (Blueprint $table) {
            $table->dropColumn('nido_nivel');
        });
    }
};
