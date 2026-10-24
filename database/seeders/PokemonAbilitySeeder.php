<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ability;
use App\Models\Pokemon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Src\Battle\Domain\Catalog\NormalizadorNombre;

/**
 * Asigna las habilidades del catálogo a cada especie desde
 * `poke habilidades.csv`. La columna `habilidades` contiene una lista separada
 * por comas (normalmente 1-3 habilidades).
 */
class PokemonAbilitySeeder extends Seeder
{
    public function run(): void
    {
        $ruta = storage_path('data/poke habilidades.csv');
        if (! is_file($ruta)) {
            return;
        }

        /** @var array<string, int> $mapa */
        $mapa = [];
        foreach (Ability::all(['id', 'name']) as $ability) {
            $mapa[NormalizadorNombre::normalizar($ability->name)] = $ability->id;
        }

        $asignaciones = [];
        foreach ($this->leerCsv($ruta) as $fila) {
            $pokemonId = (int) ($fila['id'] ?? 0);
            if ($pokemonId <= 0 || ! Pokemon::whereKey($pokemonId)->exists()) {
                continue;
            }

            $ids = [];
            foreach ($this->nombresDeHabilidades((string) ($fila['habilidades'] ?? '')) as $nombre) {
                $abilityId = $mapa[NormalizadorNombre::normalizar($nombre)] ?? null;
                if ($abilityId !== null) {
                    $ids[$abilityId] = $abilityId;
                }
            }

            if ($ids !== []) {
                $asignaciones[$pokemonId] = array_values($ids);
            }
        }

        $mapaClaves = [];
        foreach (Ability::all(['id', 'data']) as $ability) {
            foreach ((array) ($ability->data['effect_keys'] ?? []) as $clave) {
                if (is_string($clave)) {
                    $mapaClaves[$clave] = $ability->id;
                }
            }
        }

        foreach (['abilities_lote1.json', 'abilities_lote2.json', 'abilities_lote3.json'] as $archivoLote) {
            $rutaLote = storage_path('data/'.$archivoLote);
            if (! is_file($rutaLote)) {
                continue;
            }
            $lote = json_decode((string) file_get_contents($rutaLote), true, 512, JSON_THROW_ON_ERROR);
            foreach ($lote['assignments'] ?? [] as $pokemonId => $claves) {
                $id = (int) $pokemonId;
                if (! Pokemon::whereKey($id)->exists()) {
                    continue;
                }

                $ids = $asignaciones[$id] ?? [];
                foreach ((array) $claves as $clave) {
                    $abilityId = $mapaClaves[(string) $clave] ?? null;
                    if ($abilityId !== null) {
                        $ids[$abilityId] = $abilityId;
                    }
                }
                $asignaciones[$id] = array_values($ids);
            }
        }

        DB::transaction(function () use ($asignaciones): void {
            foreach ($asignaciones as $pokemonId => $abilityIds) {
                DB::table('ability_pokemon')->where('pokemon_id', $pokemonId)->delete();

                $filas = [];
                foreach ($abilityIds as $indice => $abilityId) {
                    $filas[] = [
                        'pokemon_id' => $pokemonId,
                        'ability_id' => $abilityId,
                        'slot' => $indice + 1,
                        'source' => 'poke_habilidades',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                DB::table('ability_pokemon')->insert($filas);
            }
        });

        $this->command?->info(count($asignaciones).' Pokémon con habilidades asignadas.');
    }

    /**
     * @return list<string>
     */
    private function nombresDeHabilidades(string $valor): array
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
}
