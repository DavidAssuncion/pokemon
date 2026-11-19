<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Collections;

use Src\Granjas\Domain\DataTransferObjects\TiposPokemon;
use Src\Habitats\Presentation\ColeccionTiposFamilia;
use Src\Habitats\Presentation\DTOPokemonTipo;
use Src\Shared\Collections\IntCollection;
use Src\Shared\Domain\Collection;

/**
 * Tipos conocidos por pokémon. Permite calcular la unión de tipos de un
 * conjunto de miembros sin volver a consultar la Pokédex.
 */
final class TiposPokemonCollection extends Collection
{
    public string $type = TiposPokemon::class;

    /**
     * Unión de tipos de los pokémon indicados, deduplicada por id y ordenada
     * por id (mismo orden que la unión completa de una familia).
     */
    public function tiposDe(IntCollection $pokemonIds): ColeccionTiposFamilia
    {
        $buscados = array_fill_keys($pokemonIds->all(), true);
        /** @var array<int, DTOPokemonTipo> $porId */
        $porId = [];

        foreach ($this->items as $item) {
            if (! $item instanceof TiposPokemon || ! isset($buscados[$item->pokemonId])) {
                continue;
            }

            foreach ($item->tipos as $tipo) {
                if (! $tipo instanceof DTOPokemonTipo) {
                    continue;
                }

                $porId[$tipo->id] = $tipo;
            }
        }

        ksort($porId, SORT_NUMERIC);

        return new ColeccionTiposFamilia(array_values($porId));
    }
}
