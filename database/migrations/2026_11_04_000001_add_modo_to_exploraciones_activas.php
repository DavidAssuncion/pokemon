<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * `modo` nullable: `null` equivale al comportamiento historico (MIXTO),
     * de modo que las expediciones en curso y las filas antiguas no cambian.
     * Sin indice: solo se lee junto con la fila de la expedicion.
     */
    public function up(): void
    {
        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->string('modo', 20)->nullable()->after('nivel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->dropColumn('modo');
        });
    }
};
