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
        Schema::table('habitats', function (Blueprint $table) {
            // jsonb en pgsql; json (texto) en sqlite (patrón ya usado en gym_stages).
            $table->json('mazmorra')->nullable()->after('peligro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('habitats', function (Blueprint $table) {
            $table->dropColumn('mazmorra');
        });
    }
};
