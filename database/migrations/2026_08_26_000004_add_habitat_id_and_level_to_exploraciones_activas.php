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
        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->foreignId('habitat_id')->after('equipo_id')->constrained('habitats')->onDelete('cascade');
            $table->tinyInteger('nivel')->after('habitat_id');
            $table->integer('duracion_horas')->nullable()->after('nivel');
            $table->time('hora_limite')->nullable()->after('duracion_horas');
            $table->boolean('indefinido')->default(false)->after('hora_limite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->dropForeign(['habitat_id']);
            $table->dropColumn(['habitat_id', 'nivel', 'duracion_horas', 'hora_limite', 'indefinido']);
        });
    }
};
