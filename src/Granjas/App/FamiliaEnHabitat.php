<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use Illuminate\Support\Facades\DB;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * Valida que una familia evolutiva esté asignada al hábitat de la granja.
 */
final class FamiliaEnHabitat
{
    public function asegurar(int $habitatId, int $evolutionChainId): void
    {
        $existe = DB::table('pokemon_habitat')
            ->join('pokemon', 'pokemon.id', '=', 'pokemon_habitat.pokemon_id')
            ->where('pokemon_habitat.habitat_id', $habitatId)
            ->where('pokemon.evolution_chain_id', $evolutionChainId)
            ->exists();

        if (! $existe) {
            throw new ViolacionReglaNegocio('Esa familia no está asignada a este hábitat');
        }
    }
}
