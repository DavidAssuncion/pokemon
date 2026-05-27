<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('teams') && Schema::hasColumn('teams', 'trainer_id')) {
            try {
                Schema::table('teams', function (Blueprint $table) {
                    // drop foreign if exists, then column
                    $table->dropForeign(['trainer_id']);
                    $table->dropColumn('trainer_id');
                });
            } catch (\Throwable $e) {
                // best-effort: if dropForeign fails (constraint missing), attempt to drop column alone
                try {
                    Schema::table('teams', function (Blueprint $table) {
                        $table->dropColumn('trainer_id');
                    });
                } catch (\Throwable $e) {
                    // ignore; migration should not fatal on environments where column absent
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('teams') && !Schema::hasColumn('teams', 'trainer_id')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->unsignedBigInteger('trainer_id')->nullable()->after('id');
                // no foreign key re-creation to avoid accidental references
            });
        }
    }
};
