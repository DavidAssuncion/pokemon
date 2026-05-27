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
        if (Schema::hasTable('team_members') && Schema::hasColumn('team_members', 'experience_gained')) {
            Schema::table('team_members', function (Blueprint $table) {
                try {
                    $table->dropColumn('experience_gained');
                } catch (\Throwable $e) {
                    // ignore if cannot drop
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('team_members') && !Schema::hasColumn('team_members', 'experience_gained')) {
            Schema::table('team_members', function (Blueprint $table) {
                $table->json('experience_gained')->nullable()->after('behavior');
            });
        }
    }
};
