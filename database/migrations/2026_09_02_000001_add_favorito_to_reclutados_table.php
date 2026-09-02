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
        Schema::table('reclutados', function (Blueprint $table) {
            $table->boolean('favorito')->default(false)->after('es_shiny');
        });

        Schema::table('reclutados', function (Blueprint $table) {
            $table->index(['user_id', 'favorito']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reclutados', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'favorito']);
        });

        Schema::table('reclutados', function (Blueprint $table) {
            $table->dropColumn('favorito');
        });
    }
};
