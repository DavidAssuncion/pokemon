<?php

declare(strict_types=1);

namespace Src\Habitats\Infra;

use App\Models\Pokemon;
use Illuminate\Support\Facades\DB;
use Src\Habitats\App\RecalcularNivelesMinimosHabitat;
use Src\Habitats\Domain\DataTransferObjects\MiembroCadena;
use Src\Habitats\Domain\Repositories\HabitatGestionRepositoryInterface;
use Src\Habitats\Infra\Concerns\CadenasFamiliaHabitatsTrait;
use Src\Habitats\Presentation\DTOFamiliaDisponible;
use Src\Habitats\Presentation\DTOFamiliaEliminada;
use Src\Habitats\Presentation\DTOMinLevelActualizado;
use Src\Habitats\Presentation\DTOPokemonNivelActualizado;

/**
 * Gestión Eloquent de familias en hábitats: asignar, quitar y mover de nivel.
 */
class HabitatGestionRepository implements HabitatGestionRepositoryInterface
{
    use CadenasFamiliaHabitatsTrait;

    public function assignFamily(int $habitatId, int $evolutionChainId): DTOFamiliaDisponible
    {
        $this->assertHabitatExists($habitatId);

        $members = $this->cargarMiembrosFamilia($evolutionChainId);
        $this->assertFamilyMembersExist($evolutionChainId, $members);

        $totalStages = $this->resolvedor()->totalStages($members);

        DB::transaction(function () use ($habitatId, $members, $totalStages) {
            $records = array_map(fn (MiembroCadena $member) => [
                'pokemon_id' => $member->id,
                'habitat_id' => $habitatId,
                'level' => $this->resolvedor()->levelForStage($member->stage, $totalStages),
            ], $members->all());

            DB::table('pokemon_habitat')
                ->insertOrIgnore($records);
        });

        // El nivel mínimo se deriva del tier, la evolución y la regla de
        // starters. Se recalcula aquí para que la respuesta incluya el valor
        // definitivo de cada spawn.
        app(RecalcularNivelesMinimosHabitat::class)->paraHabitat($habitatId);

        // Reconstruye la familia completa con los niveles REALES por miembro
        // (incluye ramificaciones; se leen los pivotes persistidos).
        $family = $this->buildAvailableFamilyFromChain($evolutionChainId, $members, $this->pivotesDeHabitat($habitatId, $members->ids()));

        return $family ?? throw new \LogicException("La cadena evolutiva {$evolutionChainId} no tiene pokémon base");
    }

    public function removeFamily(int $habitatId, int $evolutionChainId): DTOFamiliaEliminada
    {
        $this->assertHabitatExists($habitatId);

        $members = $this->cargarMiembrosFamilia($evolutionChainId);
        $this->assertFamilyMembersExist($evolutionChainId, $members);

        $pokemonIds = $members->ids();

        $removedCount = 0;

        DB::transaction(function () use ($habitatId, $pokemonIds, &$removedCount) {
            $removedCount = DB::table('pokemon_habitat')
                ->where('habitat_id', $habitatId)
                ->whereIn('pokemon_id', $pokemonIds)
                ->delete();
        });

        return new DTOFamiliaEliminada($habitatId, $evolutionChainId, $removedCount);
    }

    public function movePokemonToLevel(int $habitatId, int $pokemonId, int $level): DTOPokemonNivelActualizado
    {
        if ($level < 1 || $level > 3) {
            throw new \InvalidArgumentException("El nivel {$level} no es válido. Debe estar entre 1 y 3");
        }

        $this->assertHabitatExists($habitatId);

        $pokemon = Pokemon::find($pokemonId);
        if ($pokemon === null) {
            throw new \InvalidArgumentException("El pokémon {$pokemonId} no existe");
        }

        $row = DB::table('pokemon_habitat')
            ->where('pokemon_id', $pokemonId)
            ->where('habitat_id', $habitatId)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            throw new \InvalidArgumentException("El pokémon {$pokemonId} no está asignado al hábitat {$habitatId}");
        }

        $previousLevel = (int) $row->level;

        DB::table('pokemon_habitat')
            ->where('pokemon_id', $pokemonId)
            ->where('habitat_id', $habitatId)
            ->update(['level' => $level]);

        // El tier influye en el mínimo automático: se recalcula el hábitat
        // (los spawns con `manual = true` se respetan).
        app(RecalcularNivelesMinimosHabitat::class)->paraHabitat($habitatId);

        return new DTOPokemonNivelActualizado(
            habitatId: $habitatId,
            pokemonId: $pokemonId,
            previousLevel: $previousLevel,
            newLevel: $level,
        );
    }

    public function updateMinLevel(int $habitatId, int $pokemonId, int $minLevel, bool $manual): DTOMinLevelActualizado
    {
        if ($minLevel < 1 || $minLevel > 100) {
            throw new \InvalidArgumentException("El nivel mínimo {$minLevel} no es válido. Debe estar entre 1 y 100");
        }

        $this->assertHabitatExists($habitatId);

        $row = DB::table('pokemon_habitat')
            ->where('pokemon_id', $pokemonId)
            ->where('habitat_id', $habitatId)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            throw new \InvalidArgumentException("El pokémon {$pokemonId} no está asignado al hábitat {$habitatId}");
        }

        $previousMinLevel = $row->min_level !== null ? (int) $row->min_level : null;

        DB::table('pokemon_habitat')
            ->where('pokemon_id', $pokemonId)
            ->where('habitat_id', $habitatId)
            ->update([
                'min_level' => $minLevel,
                'manual' => $manual,
            ]);

        return new DTOMinLevelActualizado(
            habitatId: $habitatId,
            pokemonId: $pokemonId,
            previousMinLevel: $previousMinLevel,
            newMinLevel: $minLevel,
            manual: $manual,
        );
    }

    public function resetMinLevel(int $habitatId, int $pokemonId): DTOMinLevelActualizado
    {
        $this->assertHabitatExists($habitatId);

        $row = DB::table('pokemon_habitat')
            ->where('pokemon_id', $pokemonId)
            ->where('habitat_id', $habitatId)
            ->first();

        if ($row === null) {
            throw new \InvalidArgumentException("El pokémon {$pokemonId} no está asignado al hábitat {$habitatId}");
        }

        $previousMinLevel = $row->min_level !== null ? (int) $row->min_level : null;

        DB::table('pokemon_habitat')
            ->where('pokemon_id', $pokemonId)
            ->where('habitat_id', $habitatId)
            ->update(['manual' => false]);

        app(RecalcularNivelesMinimosHabitat::class)->paraHabitat($habitatId);

        $nuevo = DB::table('pokemon_habitat')
            ->where('pokemon_id', $pokemonId)
            ->where('habitat_id', $habitatId)
            ->value('min_level');

        return new DTOMinLevelActualizado(
            habitatId: $habitatId,
            pokemonId: $pokemonId,
            previousMinLevel: $previousMinLevel,
            newMinLevel: $nuevo !== null ? (int) $nuevo : 1,
            manual: false,
        );
    }
}
