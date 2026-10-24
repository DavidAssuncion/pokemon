<?php

declare(strict_types=1);

namespace Src\Equipos\App;

use App\Jobs\ActualizarPokedexJob;
use App\Models\Pokemon;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Support\Facades\DB;
use Src\Equipos\Domain\Collections\EquipoDefinidoCollection;
use Src\Equipos\Domain\Collections\EquipoInicialResumenCollection;
use Src\Equipos\Domain\DataTransferObjects\EquipoDefinido;
use Src\Equipos\Domain\DataTransferObjects\EquipoInicialResumen;
use Src\Shared\Collections\IntCollection;
use Src\Shared\Collections\StringCollection;

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
    public static function equiposDefinidos(): EquipoDefinidoCollection
    {
        return new EquipoDefinidoCollection([
            new EquipoDefinido(
                key: 'A',
                nombre: 'Equipo A',
                pokemonIds: new IntCollection([69, 37, 79]),
                behaviors: new StringCollection(['VANGUARDIA', 'COMBATIENTE', 'RECOLECTOR']),
            ),
            new EquipoDefinido(
                key: 'B',
                nombre: 'Equipo B',
                pokemonIds: new IntCollection([183, 228, 220]),
                behaviors: new StringCollection(['VANGUARDIA', 'COMBATIENTE', 'RASTREADOR']),
            ),
            new EquipoDefinido(
                key: 'C',
                nombre: 'Equipo C',
                pokemonIds: new IntCollection([341, 328, 285]),
                behaviors: new StringCollection(['VANGUARDIA', 'COMBATIENTE', 'RECOLECTOR']),
            ),
        ]);
    }

    public static function porClave(string $key): ?EquipoDefinido
    {
        return self::equiposDefinidos()->porClave($key);
    }

    /**
     * Equipos predefinidos con nombres de pokémon resueltos desde BD.
     */
    public static function equiposConNombres(): EquipoInicialResumenCollection
    {
        $nombresPorId = self::resolverNombres();
        $resultado = new EquipoInicialResumenCollection();

        foreach (self::equiposDefinidos() as $equipo) {
            $resultado->add(new EquipoInicialResumen(
                key: $equipo->key,
                nombre: $equipo->nombre,
                pokemonIds: $equipo->pokemonIds,
                pokemonNombres: new StringCollection($equipo->pokemonIds->map(
                    fn (int $id): string => $nombresPorId[$id] ?? 'Pokémon #'.$id,
                )),
            ));
        }

        return $resultado;
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

            foreach ($equipo->pokemonIds as $pokemonId) {
                $nombre = $nombresPorId[$pokemonId] ?? 'Pokémon #'.$pokemonId;
                $reclutado = Reclutado::create([
                    'user_id' => $userId,
                    'nombre' => $nombre,
                    'pokemon_id' => $pokemonId,
                    'exp' => [
                        'total' => 1500,
                        'tipos' => [],
                    ],
                    'es_shiny' => false,
                    'obj_equipados' => [],
                    'movimientos' => [],
                ]);
                $reclutadoIds[] = $reclutado->id;
            }

            $team = Team::create([
                'name' => $equipo->nombre,
                'user_id' => $userId,
            ]);

            foreach ($reclutadoIds as $index => $reclutadoId) {
                TeamMember::create([
                    'team_id' => $team->id,
                    'pokemon_id' => $reclutadoId,
                    'slot' => $index + 1,
                    'behavior' => $equipo->behaviors->toList()[$index],
                ]);
            }

            return $team;
        });

        foreach ($equipo->pokemonIds as $pokemonId) {
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
            foreach ($equipo->pokemonIds as $id) {
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
