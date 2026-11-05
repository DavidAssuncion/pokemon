<?php

declare(strict_types=1);

namespace Src\Habitats\Infra\Queries;

use App\Models\Pokedex;
use App\Models\Pokemon;
use App\Support\PresentadorDetalleEspecie;
use Illuminate\Support\Facades\DB;
use Src\Shared\Domain\Exceptions\RecursoNoExiste;

/**
 * Read-model del detalle completo de un Pokémon perteneciente a una familia
 * asignada a un hábitat.
 *
 * Autoriza por pertenencia: la cadena debe estar representada en
 * `pokemon_habitat` y el Pokémon debe pertenecer a esa misma cadena. Cualquier
 * combinación inválida se traduce en 404 sin filtrar si el id existe.
 */
final class DetallePokemonFamiliaQuery
{
    /**
     * @return array<string, mixed>
     */
    public function obtener(int $habitatId, int $chainId, int $pokemonId, int $userId): array
    {
        if (! $this->cadenaAsignadaAlHabitat($habitatId, $chainId)) {
            throw new RecursoNoExiste('Familia no encontrada en el hábitat');
        }

        $pokemon = Pokemon::query()
            ->where('id', $pokemonId)
            ->where('evolution_chain_id', $chainId)
            ->first();

        if ($pokemon === null) {
            throw new RecursoNoExiste('Pokémon no encontrado en la familia');
        }

        $registro = Pokedex::query()
            ->where('user_id', $userId)
            ->where('pokemon_id', $pokemonId)
            ->first();

        return PresentadorDetalleEspecie::para(
            $pokemon,
            $registro !== null && (bool) $registro->getAttribute('visto'),
            $registro !== null && (bool) $registro->getAttribute('atrapado'),
        );
    }

    private function cadenaAsignadaAlHabitat(int $habitatId, int $chainId): bool
    {
        return DB::table('pokemon_habitat')
            ->join('pokemon', 'pokemon.id', '=', 'pokemon_habitat.pokemon_id')
            ->where('pokemon_habitat.habitat_id', $habitatId)
            ->where('pokemon.evolution_chain_id', $chainId)
            ->exists();
    }
}
