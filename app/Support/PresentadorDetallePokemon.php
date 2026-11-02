<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use Src\Pokemon\Domain\Evolution\MiembroFamiliaEvolutiva;
use Src\Shared\UI\TipoBadges;

/**
 * Construye el contrato JSON del detalle de un Pokémon para el datagrid.
 *
 * Conserva los campos históricos (`types`, `stats`, hábitat) y añade la
 * información de presentación de tipos, la ubicación navegable y la familia
 * evolutiva completa. La resolución de la familia se delega en
 * `FamiliaPokemon`.
 */
final class PresentadorDetallePokemon
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     visto: bool,
     *     atrapado: bool,
     *     types: list<string>,
     *     type_details: list<array{id: int, label: string, slug: string, icon: string}>,
     *     stats: list<array{name: string, value: int}>,
     *     habitat_name: string|null,
     *     habitat_id: int|null,
     *     habitats: list<string>,
     *     location: array{
     *         habitat_id: int,
     *         habitat_name: string,
     *         habitat_image: string,
     *         province_id: int|null,
     *         province_name: string|null,
     *         url: string
     *     }|null,
     *     family: array{
     *         evolution_chain_id: int|null,
     *         members: list<array{
     *             id: int,
     *             name: string,
     *             species_id: int,
     *             stage: int,
     *             parent_id: int|null,
     *             minimum_level: int|null,
     *             branch_index: int,
     *             icon: string,
     *             is_current: bool
     *         }>
     *     }
     * }
     */
    public static function para(Pokemon $pokemon): array
    {
        $pokemon->loadMissing(['stats', 'types', 'habitats', 'habitats.province']);

        $habitat = $pokemon->habitats->first();

        return [
            'id' => (int) $pokemon->getAttribute('id'),
            'name' => (string) $pokemon->getAttribute('name'),
            'visto' => (bool) $pokemon->getAttribute('visto'),
            'atrapado' => (bool) $pokemon->getAttribute('atrapado'),
            'types' => $pokemon->types
                ->map(fn (PokemonType $type): string => $type->tipo_nombre)
                ->values()
                ->all(),
            'type_details' => self::typeDetails($pokemon),
            'stats' => $pokemon->stats
                ->sortBy(fn (PokemonStat $stat): int => $stat->stat->value)
                ->map(fn (PokemonStat $stat): array => [
                    'name' => $stat->stat_nombre,
                    'value' => $stat->base_stat,
                ])
                ->values()
                ->all(),
            'habitat_name' => $habitat?->name,
            'habitat_id' => $habitat?->id,
            'habitats' => $pokemon->habitats->pluck('name')->values()->all(),
            'location' => self::location($habitat),
            'family' => self::family($pokemon),
        ];
    }

    /**
     * @return list<array{id: int, label: string, slug: string, icon: string}>
     */
    private static function typeDetails(Pokemon $pokemon): array
    {
        /** @var list<array{id: int, label: string, slug: string, icon: string}> $detalles */
        $detalles = $pokemon->types
            ->sortBy(fn (PokemonType $type): int => $type->slot)
            ->map(function (PokemonType $type): array {
                $badge = TipoBadges::porId($type->type->value);

                return [
                    'id' => $badge['id'],
                    'label' => $badge['label'],
                    'slug' => $badge['slug'],
                    'icon' => $badge['icono'],
                ];
            })
            ->values()
            ->all();

        return $detalles;
    }

    /**
     * @return array{
     *     habitat_id: int,
     *     habitat_name: string,
     *     habitat_image: string,
     *     province_id: int|null,
     *     province_name: string|null,
     *     url: string
     * }|null
     */
    private static function location(?Habitat $habitat): ?array
    {
        if ($habitat === null) {
            return null;
        }

        return [
            'habitat_id' => (int) $habitat->getAttribute('id'),
            'habitat_name' => (string) $habitat->getAttribute('name'),
            'habitat_image' => '/habitats-img/'.$habitat->getAttribute('id').'.webp?v=1',
            'province_id' => $habitat->getAttribute('province_id') === null
                ? null
                : (int) $habitat->getAttribute('province_id'),
            'province_name' => $habitat->province?->name,
            'url' => '/habitats/'.$habitat->getAttribute('id'),
        ];
    }

    /**
     * @return array{
     *     evolution_chain_id: int|null,
     *     members: list<array{
     *         id: int,
     *         name: string,
     *         species_id: int,
     *         stage: int,
     *         parent_id: int|null,
     *         minimum_level: int|null,
     *         branch_index: int,
     *         icon: string,
     *         is_current: bool
     *     }>
     * }
     */
    private static function family(Pokemon $pokemon): array
    {
        $currentId = (int) $pokemon->getAttribute('id');
        $chainId = $pokemon->getAttribute('evolution_chain_id');

        /** @var list<array{
         *     id: int,
         *     name: string,
         *     species_id: int,
         *     stage: int,
         *     parent_id: int|null,
         *     minimum_level: int|null,
         *     branch_index: int,
         *     icon: string,
         *     is_current: bool
         * }> $miembros
         */
        $miembros = [];

        foreach (FamiliaPokemon::para($pokemon)->miembros() as $miembro) {
            $miembros[] = self::familyMember($miembro, $currentId);
        }

        if ($miembros === []) {
            $miembros[] = self::fallbackCurrent($pokemon, $currentId);
        }

        return [
            'evolution_chain_id' => $chainId === null ? null : (int) $chainId,
            'members' => $miembros,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     species_id: int,
     *     stage: int,
     *     parent_id: int|null,
     *     minimum_level: int|null,
     *     branch_index: int,
     *     icon: string,
     *     is_current: bool
     * }
     */
    private static function familyMember(MiembroFamiliaEvolutiva $miembro, int $currentId): array
    {
        return [
            'id' => $miembro->id,
            'name' => $miembro->nombre,
            'species_id' => $miembro->speciesId,
            'stage' => $miembro->stage,
            'parent_id' => $miembro->parentId,
            'minimum_level' => $miembro->nivelMinimo,
            'branch_index' => $miembro->branchIndex,
            'icon' => '/images/iconos_webp/'.$miembro->id.'.webp',
            'is_current' => $miembro->id === $currentId,
        ];
    }

    /**
     * Salvaguarda del contrato `family.members` (siempre al menos el actual).
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     species_id: int,
     *     stage: int,
     *     parent_id: int|null,
     *     minimum_level: int|null,
     *     branch_index: int,
     *     icon: string,
     *     is_current: bool
     * }
     */
    private static function fallbackCurrent(Pokemon $pokemon, int $currentId): array
    {
        return [
            'id' => $currentId,
            'name' => (string) $pokemon->getAttribute('name'),
            'species_id' => (int) $pokemon->getAttribute('species_id'),
            'stage' => 1,
            'parent_id' => null,
            'minimum_level' => null,
            'branch_index' => 0,
            'icon' => '/images/iconos_webp/'.$currentId.'.webp',
            'is_current' => true,
        ];
    }
}
