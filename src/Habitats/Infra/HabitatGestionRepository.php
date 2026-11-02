<?php

declare(strict_types=1);

namespace Src\Habitats\Infra;

use App\Models\Pokemon;
use Illuminate\Support\Facades\DB;
use Src\Habitats\Domain\DataTransferObjects\MiembroCadena;
use Src\Habitats\Domain\Repositories\HabitatGestionRepositoryInterface;
use Src\Habitats\Infra\Concerns\CadenasFamiliaHabitatsTrait;
use Src\Habitats\Presentation\DTOFamiliaDisponible;
use Src\Habitats\Presentation\DTOFamiliaEliminada;
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
                ->upsert($records, ['pokemon_id', 'habitat_id'], ['level']);
        });

        // Reconstruye la familia completa con los niveles REALES por miembro (incluye ramificaciones:
        // levelForStage aplica el mismo reparto que el upsert anterior).
        $family = $this->buildAvailableFamilyFromChain($evolutionChainId, $members);

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

        return new DTOPokemonNivelActualizado(
            habitatId: $habitatId,
            pokemonId: $pokemonId,
            previousLevel: $previousLevel,
            newLevel: $level,
        );
    }
}
