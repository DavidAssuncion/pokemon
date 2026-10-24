<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Catálogo de movimientos. No guarda PP: los movimientos no se consumen.
     * `accuracy` NULL = nunca falla; 100 = acierta siempre por defecto.
     */
    public function up(): void
    {
        Schema::create('moves', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->smallInteger('num')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->smallInteger('type_id');
            $table->string('category');
            $table->smallInteger('base_power')->default(0);
            $table->smallInteger('accuracy')->nullable()->default(100);
            $table->smallInteger('priority')->default(0);
            $table->string('target')->default('normal');
            $table->smallInteger('crit_ratio')->default(1);
            $table->boolean('will_crit')->default(false);
            $table->string('status_effect')->nullable();
            $table->smallInteger('status_chance')->default(100);
            $table->smallInteger('multihit_min')->default(1);
            $table->smallInteger('multihit_max')->default(1);
            $table->boolean('multiaccuracy')->default(false);
            $table->smallInteger('drain_percent')->default(0);
            $table->smallInteger('recoil_percent')->default(0);
            $table->smallInteger('heal_percent')->default(0);
            $table->string('effect_key')->nullable();
            $table->json('flags')->nullable();
            $table->json('secondaries')->nullable();
            $table->json('self_effects')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index('type_id');
            $table->index('category');
            $table->index('target');
            $table->index('effect_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moves');
    }
};
