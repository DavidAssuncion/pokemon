<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * `archived_at` nullable: `null` significa visible en panel y pagina;
     * con valor, la expedicion queda archivada y solo aparece en el historial.
     * El indice compuesto sirve al filtro por usuario y al orden del historial.
     */
    public function up(): void
    {
        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('regreso');
            $table->index(['user_id', 'archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
