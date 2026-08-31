<?php

declare(strict_types=1);

namespace Src\Equipos\App;

use App\Jobs\ActualizarPokedexJob;
use App\Models\Pokemon;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Support\Facades\DB;

/**
 * Creación del equipo inicial de onboarding en una transacción.
 *
 * Reutilizable desde el seeder de reclutados. Contiene la definición de los
 * 3 equipos predefinidos y la lógica transaccional de creación.
 *
 * Comportamiento por slot (documentado):
 * - Equipo A (Bellsprout/Vulpix/Slowpoke): slot1 VANGUARDIA, slot2 COMBATIENTE, slot3 RECOLECTOR
 * - Equipo B (Marill/Houndour/Swinub):     slot1 VANGUARDIA, slot2 COMBATIENTE, slot3 RASTREADOR
 * - Equipo C (Corphish/Trapinch/Shroomish): slot1 VANGUARDIA, slot2 COMBATIENTE, slot3 RECOLECTOR
 * Equipo B tiene RASTREADOR en slot3 para variedad de roles.
 */
final class CrearEquipoInicial
{
    /**
     * @return list<array{key: string, nombre: string, pokemon_ids: list<int>, behaviors: list<string>}>
     */
    public static function equiposDefinidos(): array
    {
        return [
            [
                'key' => 'A',
                'nombre' => 'Equipo A',
                'pokemon_ids' => [69, 37, 79],
                'behaviors' => ['VANGUARDIA', 'COMBATIENTE', 'RECOLECTOR'],
            ],
            [
                'key' => 'B',
                'nombre' => 'Equipo B',
                'pokemon_ids' => [183, 228, 220],
                'behaviors' => ['VANGUARDIA', 'COMBATIENTE', 'RASTREADOR'],
            ],
            [
                'key' => 'C',
                'nombre' => 'Equipo C',
                'pokemon_ids' => [341, 328, 285],
                'behaviors' => ['VANGUARDIA', 'COMBATIENTE', 'RECOLECTOR'],
            ],
        ];
    }

    /**
     * @return array{key: string, nombre: string, pokemon_ids: list<int>, behaviors: list<string>}|null
     */
    public static function porClave(string $key): ?array
    {
        $equipos = self::equiposDefinidos();
        foreach ($equipos as $equipo) {
            if ($equipo['key'] === $key) {
                return $equipo;
            }
        }

        return null;
    }

    /**
     * Equipos predefinidos con nombres de pokémon resueltos desde BD.
     *
     * @return list<array{key: string, nombre: string, pokemon_ids: list<int>, pokemon_nombres: list<string>}>
     */
    public static function equiposConNombres(): array
    {
        $nombresPorId = self::resolverNombres();

        return array_map(function (array $equipo) use ($nombresPorId): array {
            return [
                'key' => $equipo['key'],
                'nombre' => $equipo['nombre'],
                'pokemon_ids' => $equipo['pokemon_ids'],
                'pokemon_nombres' => array_map(
                    fn (int $id): string => $nombresPorId[$id] ?? 'Pokémon #'.$id,
                    $equipo['pokemon_ids'],
                ),
            ];
        }, self::equiposDefinidos());
    }

    /**
     * Crea el equipo inicial en una transacción: 3 reclutados, 1 team, 3 members.
     */
    public static function crear(int $userId, string $teamKey): Team
    {
        $equipo = self::porClave($teamKey);
        if ($equipo === null) {
            throw new \InvalidArgumentException("Clave de equipo inválida: {$teamKey}");
        }

        $nombresPorId = self::resolverNombres();

        $team = DB::transaction(function () use ($userId, $equipo, $nombresPorId): Team {
            $reclutadoIds = [];

            foreach ($equipo['pokemon_ids'] as $pokemonId) {
                $nombre = $nombresPorId[$pokemonId] ?? 'Pokémon #'.$pokemonId;
                $reclutado = Reclutado::create([
                    'user_id' => $userId,
                    'nombre' => $nombre,
                    'pokemon_id' => $pokemonId,
                    'exp' => [],
                    'es_shiny' => false,
                    'obj_equipados' => [],
                    'movimientos' => [],
                ]);
                $reclutadoIds[] = $reclutado->id;
            }

            $team = Team::create([
                'name' => $equipo['nombre'],
                'user_id' => $userId,
            ]);

            foreach ($reclutadoIds as $index => $reclutadoId) {
                TeamMember::create([
                    'team_id' => $team->id,
                    'pokemon_id' => $reclutadoId,
                    'slot' => $index + 1,
                    'behavior' => $equipo['behaviors'][$index],
                ]);
            }

            return $team;
        });

        foreach ($equipo['pokemon_ids'] as $pokemonId) {
            ActualizarPokedexJob::dispatch($userId, $pokemonId, 'RECLUTADO');
        }

        return $team;
    }

    /**
     * @return array<int, string>
     */
    private static function resolverNombres(): array
    {
        $ids = [];
        foreach (self::equiposDefinidos() as $equipo) {
            foreach ($equipo['pokemon_ids'] as $id) {
                $ids[$id] = $id;
            }
        }

        $result = [];
        $pokemons = Pokemon::whereIn('id', array_keys($ids))->get(['id', 'name']);
        foreach ($pokemons as $pokemon) {
            $result[$pokemon->id] = $pokemon->name;
        }

        return $result;
    }
}
