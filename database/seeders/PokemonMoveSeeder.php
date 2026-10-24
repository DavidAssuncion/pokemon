<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Move;
use App\Models\Pokemon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Battle\Domain\Catalog\NormalizadorNombre;

/**
 * Asigna los movimientos del catálogo a cada especie desde
 * storage/data/poke habilidades.csv y los lotes de learnsets derivados de
 * PokeAPI (lotes 1-8).
 *
 * La columna `movimientos` contiene 0-2 nombres separados por comas. El
 * seeder valida TODAS las filas antes de escribir y falla con el listado de
 * nombres/IDs desconocidos para no dejar relaciones a medias.
 */
class PokemonMoveSeeder extends Seeder
{
    /**
     * Movimientos del Bloque 5 para especies concretas. Se añaden después del
     * CSV para que el catálogo de interacción con objetos/habilidades sea
     * utilizable sin editar el fichero de datos original.
     *
     * @var array<int, list<string>>
     */
    private const ASIGNACIONES_EXTRA = [
        23 => ['jugo_gastrico'],
        24 => ['jugo_gastrico', 'embargo'],
        52 => ['ladron', 'desarme', 'reciclaje'],
        53 => ['ladron', 'desarme', 'truco'],
        64 => ['truco', 'intercambio', 'imitacion'],
        65 => ['truco', 'zona_magica', 'intercambio', 'imitacion'],
        69 => ['jugo_gastrico'],
        70 => ['jugo_gastrico'],
        71 => ['jugo_gastrico'],
        76 => ['explosion'],
        94 => ['sustituto', 'embargo'],
        101 => ['autodestruccion'],
        110 => ['explosion', 'autodestruccion'],
        120 => ['camuflaje'],
        121 => ['camuflaje', 'zona_magica'],
        122 => ['sustituto', 'zona_magica', 'imitacion'],
        137 => ['camuflaje', 'reciclaje'],
        197 => ['embargo'],
        198 => ['ladron', 'desarme'],
        205 => ['explosion'],
        215 => ['desarme', 'ladron'],
        344 => ['explosion'],
        376 => ['explosion'],
        426 => ['explosion'],
        435 => ['explosion'],
        437 => ['explosion'],
        462 => ['explosion'],
        476 => ['explosion'],
        623 => ['explosion'],
    ];

    public function run(): void
    {
        $ruta = storage_path('data/poke habilidades.csv');
        if (! is_file($ruta)) {
            return;
        }

        $mapaMovimientos = $this->mapaMovimientos();
        $filas = $this->leerCsv($ruta);
        $asignaciones = [];
        $movimientosFaltantes = [];
        $pokemonFaltantes = [];

        foreach ($filas as $fila) {
            $pokemonId = (int) ($fila['id'] ?? 0);
            if ($pokemonId <= 0) {
                continue;
            }

            $nombres = $this->nombresDeMovimientos((string) ($fila['movimientos'] ?? ''));
            if ($nombres === []) {
                continue;
            }

            if (! Pokemon::whereKey($pokemonId)->exists()) {
                $pokemonFaltantes[] = $pokemonId;

                continue;
            }

            $movimientos = [];
            foreach ($nombres as $nombre) {
                $clave = NormalizadorNombre::normalizar($nombre);
                $clave = $this->alias($clave);
                $moveId = $mapaMovimientos[$clave] ?? null;

                if ($moveId === null) {
                    $movimientosFaltantes[] = "{$pokemonId}: {$nombre}";

                    continue;
                }

                $movimientos[$moveId] = $moveId;
            }

            if ($movimientos !== []) {
                $asignaciones[$pokemonId] = array_values($movimientos);
            }
        }

        foreach ($this->asignacionesExtra() as $pokemonId => $slugs) {
            if (! Pokemon::whereKey($pokemonId)->exists()) {
                $pokemonFaltantes[] = $pokemonId;

                continue;
            }

            $movimientos = $asignaciones[$pokemonId] ?? [];
            foreach ($slugs as $slug) {
                $clave = NormalizadorNombre::normalizar(str_replace('_', ' ', $slug));
                $moveId = $mapaMovimientos[$clave] ?? null;
                if ($moveId === null) {
                    $movimientosFaltantes[] = "{$pokemonId}: {$slug}";

                    continue;
                }

                $movimientos[$moveId] = $moveId;
            }

            if ($movimientos !== []) {
                // Sin límite en el catálogo disponible para selección; el loadout
                // se limita a 6 en la UI/validación.
                $asignaciones[$pokemonId] = array_values(array_unique($movimientos));
            }
        }

        if ($movimientosFaltantes !== [] || $pokemonFaltantes !== []) {
            throw new RuntimeException(
                'Asignación incompleta. Movimientos desconocidos: '
                .implode(', ', $movimientosFaltantes)
                .'. Pokémon inexistentes: '
                .implode(', ', array_unique($pokemonFaltantes)),
            );
        }

        DB::transaction(function () use ($asignaciones): void {
            foreach ($asignaciones as $pokemonId => $moveIds) {
                DB::table('pokemon_moves')->where('pokemon_id', $pokemonId)->delete();

                $filas = [];
                foreach ($moveIds as $indice => $moveId) {
                    $filas[] = [
                        'pokemon_id' => $pokemonId,
                        'move_id' => $moveId,
                        'slot' => $indice + 1,
                        'source' => 'poke_habilidades',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                DB::table('pokemon_moves')->insert($filas);
            }
        });

        $this->command?->info(count($asignaciones).' Pokémon con movimientos asignados.');
    }

    /**
     * Combina las asignaciones manuales con el lote ofensivo derivado de
     * PokeAPI. El JSON solo contiene movimientos de la primera tanda.
     *
     * @return array<int, list<string>>
     */
    private function asignacionesExtra(): array
    {
        $extra = self::ASIGNACIONES_EXTRA;
        foreach (['pokemon_moves_lote1.json', 'pokemon_moves_lote2.json', 'pokemon_moves_lote3.json', 'pokemon_moves_lote4.json', 'pokemon_moves_lote5.json', 'pokemon_moves_lote6.json', 'pokemon_moves_lote7.json', 'pokemon_moves_lote8.json', 'pokemon_moves_lote9.json', 'pokemon_moves_lote10.json'] as $archivo) {
            $ruta = storage_path('data/'.$archivo);
            if (! is_file($ruta)) {
                continue;
            }

            $lote = json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($lote)) {
                continue;
            }

            foreach ($lote as $pokemonId => $slugs) {
                if (! is_array($slugs)) {
                    continue;
                }

                $id = (int) $pokemonId;
                if (! Pokemon::whereKey($id)->exists()) {
                    continue;
                }

                $extra[$id] = array_values(array_unique(array_merge(
                    $extra[$id] ?? [],
                    array_values(array_filter($slugs, 'is_string')),
                )));
            }
        }

        return $extra;
    }

    /**
     * @return array<string, int> nombre normalizado => move_id
     */
    private function mapaMovimientos(): array
    {
        $mapa = [];
        foreach (Move::all(['id', 'name', 'slug']) as $move) {
            $mapa[NormalizadorNombre::normalizar($move->name)] = $move->id;
            $mapa[NormalizadorNombre::normalizar(str_replace('_', ' ', $move->slug))] = $move->id;
        }

        return $mapa;
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function leerCsv(string $ruta): array
    {
        $gestor = fopen($ruta, 'rb');
        if ($gestor === false) {
            return [];
        }

        $cabecera = fgetcsv($gestor);
        if ($cabecera === false) {
            fclose($gestor);

            return [];
        }

        $cabecera[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cabecera[0]);
        $filas = [];

        while (($valores = fgetcsv($gestor)) !== false) {
            $fila = [];
            foreach ($cabecera as $indice => $columna) {
                $fila[(string) $columna] = $valores[$indice] ?? null;
            }
            $filas[] = $fila;
        }

        fclose($gestor);

        return $filas;
    }

    /**
     * @return list<string>
     */
    private function nombresDeMovimientos(string $valor): array
    {
        $nombres = [];
        foreach (explode(',', $valor) as $nombre) {
            $nombre = trim($nombre);
            if ($nombre !== '') {
                $nombres[] = $nombre;
            }
        }

        return array_values(array_unique($nombres));
    }

    private function alias(string $clave): string
    {
        return match ($clave) {
            'patada salto alto' => 'patada salto alta',
            default => $clave,
        };
    }
}
