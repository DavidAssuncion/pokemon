<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('moves', function (Blueprint $table) {
            $table->unsignedSmallInteger('nivel_requerido')
                ->default(1)
                ->after('base_power');
        });
    }

    public function down(): void
    {
        Schema::table('moves', function (Blueprint $table) {
            $table->dropColumn('nivel_requerido');
        });
    }
};
