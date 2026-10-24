<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('reclutados', function (Blueprint $table) {
            $table->foreignId('habilidad_activa_id')
                ->nullable()
                ->after('movimientos')
                ->constrained('abilities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reclutados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('habilidad_activa_id');
        });
    }
};
