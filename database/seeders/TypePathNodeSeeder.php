<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Move;
use App\Support\SendaNiveles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea un nodo de Senda por cada movimiento que aparece en algún learnset.
 *
 * Los nodos no guardan coste ni fuente: el coste se resuelve con
 * App\Support\SendaCostes y el nivel requerido se calcula con SendaNiveles.
 */
class TypePathNodeSeeder extends Seeder
{
    public function run(): void
    {
        $moveIds = DB::table('pokemon_moves')
            ->distinct()
            ->pluck('move_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $moves = Move::query()
            ->whereIn('id', $moveIds)
            ->get(['id', 'slug', 'name', 'type_id', 'category', 'base_power', 'nivel_requerido']);

        $agrupados = [];
        foreach ($moves as $move) {
            $tipoId = (int) $move->type_id;
            $categoria = self::categoria($move);
            $agrupados[$tipoId][$categoria][] = $move;
        }

        $filas = [];
        foreach ($agrupados as $tipoId => $categorias) {
            foreach ($categorias as $categoria => $movimientos) {
                usort($movimientos, static function (Move $a, Move $b): int {
                    return [
                        SendaNiveles::para($a),
                        (int) $a->base_power,
                        (string) $a->name,
                    ] <=> [
                        SendaNiveles::para($b),
                        (int) $b->base_power,
                        (string) $b->name,
                    ];
                });

                foreach ($movimientos as $orden => $move) {
                    $filas[] = [
                        'type_id' => $tipoId,
                        'move_id' => (int) $move->id,
                        'category' => $categoria,
                        'required_level' => SendaNiveles::para($move),
                        'sort_order' => $orden + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        DB::transaction(function () use ($filas): void {
            DB::table('type_path_nodes')->delete();

            foreach (array_chunk($filas, 1000) as $lote) {
                DB::table('type_path_nodes')->insert($lote);
            }
        });

        $this->command?->info(count($filas).' nodos de Senda creados.');
    }

    private static function categoria(Move $move): string
    {
        return in_array($move->category, ['fisico', 'especial', 'estado'], true)
            ? (string) $move->category
            : 'estado';
    }
}
