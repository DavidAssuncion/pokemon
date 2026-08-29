<?php

declare(strict_types=1);

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
        DB::transaction(function (): void {
            foreach (DB::table('reclutados_exp_tipo')->get() as $expTipo) {
                $reclutado = DB::table('reclutados')->where('id', $expTipo->reclutado_id)->first();
                if ($reclutado === null) {
                    continue;
                }

                /** @var array{total?: int, tipos?: array<string, int>} $exp */
                $exp = json_decode((string) ($reclutado->exp ?? '{}'), true) ?: [];
                $exp['tipos'][$expTipo->tipo] = (int) $expTipo->cantidad;

                DB::table('reclutados')
                    ->where('id', $reclutado->id)
                    ->update(['exp' => json_encode($exp)]);
            }
        });

        Schema::dropIfExists('reclutados_exp_tipo');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('reclutados_exp_tipo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reclutado_id')->constrained('reclutados')->onDelete('cascade');
            $table->string('tipo');
            $table->unsignedBigInteger('cantidad')->default(0);
            $table->timestamps();
            $table->unique(['reclutado_id', 'tipo']);
        });

        // Restauración best-effort: vuelca exp.tipos a filas de reclutados_exp_tipo.
        DB::transaction(function (): void {
            foreach (DB::table('reclutados')->get() as $reclutado) {
                /** @var array{total?: int, tipos?: array<string, int>} $exp */
                $exp = json_decode((string) ($reclutado->exp ?? '{}'), true) ?: [];

                foreach ((array) ($exp['tipos'] ?? []) as $tipo => $cantidad) {
                    DB::table('reclutados_exp_tipo')->insert([
                        'reclutado_id' => $reclutado->id,
                        'tipo' => (string) $tipo,
                        'cantidad' => (int) $cantidad,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }
};
