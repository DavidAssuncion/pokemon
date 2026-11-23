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
            // Forma desde la que se entró al grupo de versiones. Es la forma
            // "original" y siempre se puede recuperar gratis.
            $table->foreignId('forma_original_id')
                ->nullable()
                ->after('pokemon_id')
                ->constrained('pokemon')
                ->nullOnDelete();
        });

        Schema::create('reclutado_formas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reclutado_id')->constrained('reclutados')->cascadeOnDelete();
            $table->foreignId('pokemon_id')->constrained('pokemon')->cascadeOnDelete();
            $table->timestamp('unlocked_at')->useCurrent();
            $table->timestamps();

            $table->unique(['reclutado_id', 'pokemon_id']);
            $table->index('pokemon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reclutado_formas');

        Schema::table('reclutados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('forma_original_id');
        });
    }
};
