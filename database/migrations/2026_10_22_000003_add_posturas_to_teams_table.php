<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Postura por slot del equipo: { "1": "atacar", "2": "guardia", ... }.
     * La batalla puede cambiarla antes de cada ronda.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->json('posturas')->nullable()->after('formacion');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('posturas');
        });
    }
};
