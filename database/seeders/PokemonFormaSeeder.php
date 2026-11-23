<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Siembra las formas alternativas que no entran en el seeder principal
 * (Deoxys Attack/Defense/Speed, Giratina Origin).
 *
 * El seeder principal omite las formas no regionales; este seeder las crea
 * solo para los grupos de `config/pokemon_versions.php` y copia habilidades y
 * movimientos de la forma base, que ya está sembrada.
 */
class PokemonFormaSeeder extends Seeder
{
    public function run(): void
    {
        $pokemonRows = $this->indexarPorId(storage_path('data/pokemon.csv'));
        $speciesRows = $this->indexarPorId(storage_path('data/pokemon_species.csv'));
        $stats = $this->agruparPorPokemon(storage_path('data/pokemon_stats.csv'));
        $types = $this->agruparPorPokemon(storage_path('data/pokemon_types.csv'));

        /** @var array<string, array<int, int|string>> $grupos */
        $grupos = config('pokemon_versions.grupos', []);

        foreach ($grupos as $grupo) {
            if (! is_array($grupo)) {
                continue;
            }

            $ids = array_values(array_map(static fn (int|string $id): int => (int) $id, $grupo));
            $baseId = $this->primeraExistente($ids);

            if ($baseId === null) {
                continue;
            }

            foreach ($ids as $formId) {
                if ($formId === $baseId || $this->existePokemon($formId)) {
                    continue;
                }

                $row = $pokemonRows[$formId] ?? null;
                if ($row === null) {
                    continue;
                }

                $speciesId = (int) $row['species_id'];
                $species = $speciesRows[$speciesId] ?? null;
                if ($species === null) {
                    continue;
                }

                DB::table('pokemon')->insert([
                    'id' => $formId,
                    'name' => Str::ucfirst($row['name']),
                    'species_id' => $speciesId,
                    'height' => (int) $row['height'],
                    'weight' => (int) $row['weight'],
                    'base_experience' => (int) $row['base_experience'],
                    'capture_rate' => (int) $species['capture_rate'],
                    'hatch' => ! empty($species['hatch_counter']) ? (int) $species['hatch_counter'] : null,
                    'evolution_chain_id' => ! empty($species['evolution_chain_id'])
                        ? (int) $species['evolution_chain_id']
                        : null,
                ]);

                $this->sembrarStats($formId, $stats[$formId] ?? []);
                $this->sembrarTypes($formId, $types[$formId] ?? []);
                $this->copiarHabilidades($baseId, $formId);
                $this->copiarMovimientos($baseId, $formId);
            }
        }
    }

    /**
     * @param  list<array<string, string>>  $filas
     */
    private function sembrarStats(int $pokemonId, array $filas): void
    {
        DB::table('pokemon_stats')->where('pokemon_id', $pokemonId)->delete();

        $registros = [];
        foreach ($filas as $fila) {
            $registros[] = [
                'pokemon_id' => $pokemonId,
                'stat' => (int) $fila['stat_id'],
                'base_stat' => (int) $fila['base_stat'],
                'effort' => (int) $fila['effort'],
            ];
        }

        if ($registros !== []) {
            DB::table('pokemon_stats')->insert($registros);
        }
    }

    /**
     * @param  list<array<string, string>>  $filas
     */
    private function sembrarTypes(int $pokemonId, array $filas): void
    {
        DB::table('pokemon_types')->where('pokemon_id', $pokemonId)->delete();

        $registros = [];
        foreach ($filas as $fila) {
            $registros[] = [
                'pokemon_id' => $pokemonId,
                'type' => (int) $fila['type_id'],
                'slot' => (int) $fila['slot'],
            ];
        }

        if ($registros !== []) {
            DB::table('pokemon_types')->insert($registros);
        }
    }

    private function copiarHabilidades(int $baseId, int $formId): void
    {
        DB::table('ability_pokemon')->where('pokemon_id', $formId)->delete();

        $filas = DB::table('ability_pokemon')
            ->where('pokemon_id', $baseId)
            ->get(['ability_id', 'slot', 'source']);

        foreach ($filas as $fila) {
            DB::table('ability_pokemon')->insert([
                'pokemon_id' => $formId,
                'ability_id' => $fila->ability_id,
                'slot' => $fila->slot,
                'source' => $fila->source,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function copiarMovimientos(int $baseId, int $formId): void
    {
        DB::table('pokemon_moves')->where('pokemon_id', $formId)->delete();

        $filas = DB::table('pokemon_moves')
            ->where('pokemon_id', $baseId)
            ->get(['move_id', 'slot', 'source', 'level']);

        foreach ($filas as $fila) {
            DB::table('pokemon_moves')->insert([
                'pokemon_id' => $formId,
                'move_id' => $fila->move_id,
                'slot' => $fila->slot,
                'source' => $fila->source,
                'level' => $fila->level,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function existePokemon(int $pokemonId): bool
    {
        return DB::table('pokemon')->where('id', $pokemonId)->exists();
    }

    /**
     * @param  list<int>  $ids
     */
    private function primeraExistente(array $ids): ?int
    {
        foreach ($ids as $id) {
            if ($this->existePokemon($id)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function indexarPorId(string $ruta): array
    {
        $filas = [];
        foreach ($this->leerCsv($ruta) as $fila) {
            $filas[(int) $fila['id']] = $fila;
        }

        return $filas;
    }

    /**
     * @return array<int, list<array<string, string>>>
     */
    private function agruparPorPokemon(string $ruta): array
    {
        $filas = [];
        foreach ($this->leerCsv($ruta) as $fila) {
            $filas[(int) $fila['pokemon_id']][] = $fila;
        }

        return $filas;
    }

    /**
     * @return list<array<string, string>>
     */
    private function leerCsv(string $ruta): array
    {
        if (! is_file($ruta)) {
            return [];
        }

        $data = [];
        $headers = [];
        if (($handle = fopen($ruta, 'r')) === false) {
            return [];
        }

        $linea = 0;
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($linea === 0) {
                $headers = $row;
            } elseif (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
            $linea++;
        }

        fclose($handle);

        return $data;
    }
}
