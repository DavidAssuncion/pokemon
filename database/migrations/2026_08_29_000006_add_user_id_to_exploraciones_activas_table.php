<?php

declare(strict_types=1);

use App\Support\LegacyUserMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
        });

        $legacyUserId = LegacyUserMigrator::ensureLegacyUserId();
        if ($legacyUserId !== null) {
            DB::table('exploraciones_activas')->whereNull('user_id')->update(['user_id' => $legacyUserId]);
        }

        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // Postgres no indexa FK automáticamente: índices explícitos para las
            // consultas por usuario, equipo y hábitat.
            $table->index('user_id');
            $table->index('equipo_id');
            $table->index('habitat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['equipo_id']);
            $table->dropIndex(['habitat_id']);
            $table->dropColumn('user_id');
        });
    }
};
