<?php

declare(strict_types=1);

use App\Support\LegacyUserMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\Shared\Domain\SlugTipo;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $legacyUserId = LegacyUserMigrator::ensureLegacyUserId();

        if ($legacyUserId !== null) {
            DB::transaction(function () use ($legacyUserId): void {
                foreach (DB::table('caramelos')->get() as $caramelo) {
                    DB::table('player_inventory')->insert([
                        'user_id' => $legacyUserId,
                        'item_key' => "familia:{$caramelo->evolution_chain_id}",
                        'cantidad' => (int) $caramelo->cantidad,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach (DB::table('caramelos_ev')->get() as $caramelo) {
                    DB::table('player_inventory')->insert([
                        'user_id' => $legacyUserId,
                        'item_key' => "ev:{$caramelo->stat}",
                        'cantidad' => (int) $caramelo->cantidad,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach (DB::table('caramelos_tipo')->get() as $caramelo) {
                    DB::table('player_inventory')->insert([
                        'user_id' => $legacyUserId,
                        'item_key' => 'tipo:'.SlugTipo::de((string) $caramelo->tipo),
                        'cantidad' => (int) $caramelo->cantidad,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        }

        Schema::dropIfExists('caramelos');
        Schema::dropIfExists('caramelos_ev');
        Schema::dropIfExists('caramelos_tipo');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreación best-effort: restaura desde player_inventory sin borrar los
        // items (la tabla ya la usa la fase C); las columnas se recrean sin FK
        // (evolution_chains no existe desde el bug 23503).
        Schema::create('caramelos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evolution_chain_id');
            $table->unsignedInteger('cantidad')->default(0);
            $table->timestamps();
            $table->unique('evolution_chain_id');
        });

        Schema::create('caramelos_ev', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('stat');
            $table->unsignedBigInteger('cantidad')->default(0);
            $table->timestamps();
            $table->unique('stat');
        });

        Schema::create('caramelos_tipo', function (Blueprint $table) {
            $table->id();
            $table->string('tipo');
            $table->unsignedBigInteger('cantidad')->default(0);
            $table->timestamps();
            $table->unique('tipo');
        });

        DB::transaction(function (): void {
            foreach (DB::table('player_inventory')->where('item_key', 'like', 'familia:%')->get() as $item) {
                DB::table('caramelos')->insert([
                    'evolution_chain_id' => (int) substr($item->item_key, strlen('familia:')),
                    'cantidad' => (int) $item->cantidad,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }

            foreach (DB::table('player_inventory')->where('item_key', 'like', 'ev:%')->get() as $item) {
                DB::table('caramelos_ev')->insert([
                    'stat' => (int) substr($item->item_key, strlen('ev:')),
                    'cantidad' => (int) $item->cantidad,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }

            foreach (DB::table('player_inventory')->where('item_key', 'like', 'tipo:%')->get() as $item) {
                DB::table('caramelos_tipo')->insert([
                    'tipo' => substr($item->item_key, strlen('tipo:')),
                    'cantidad' => (int) $item->cantidad,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }
        });
    }
};
