<?php

declare(strict_types=1);

namespace Src\Granjas\Infra;

use App\Enums\TipoEnum;
use Illuminate\Support\Facades\DB;
use Src\Granjas\Domain\Collections\TiposPokemonCollection;
use Src\Granjas\Domain\DataTransferObjects\TiposPokemon;
use Src\Granjas\Domain\Ports\PokedexLecturaInterface;
use Src\Habitats\Presentation\ColeccionTiposFamilia;
use Src\Habitats\Presentation\DTOPokemonTipo;
use Src\Shared\Collections\IntCollection;

/**
 * Lectura de la Pokédex para el módulo de granjas.
 *
 * Usa el query builder con `user_id` explícito: nunca el modelo `Pokedex`,
 * cuyo scope `BelongsToUser` depende de `Auth` y quedaría inactivo en jobs,
 * comandos o cualquier contexto sin sesión.
 */
final class PokedexLectura implements PokedexLecturaInterface
{
    public function vistos(int $userId, IntCollection $candidatos): IntCollection
    {
        $ids = $candidatos->all();

        if ($ids === []) {
            return new IntCollection();
        }

        $vistos = DB::table('pokedex')
            ->where('user_id', $userId)
            ->where('visto', true)
            ->whereIn('pokemon_id', $ids)
            ->pluck('pokemon_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return new IntCollection(array_values($vistos));
    }

    public function cadenasConAlgunVisto(int $userId, IntCollection $cadenas): IntCollection
    {
        $ids = $cadenas->all();

        if ($ids === []) {
            return new IntCollection();
        }

        $visibles = DB::table('pokemon')
            ->join('pokedex', 'pokedex.pokemon_id', '=', 'pokemon.id')
            ->where('pokedex.user_id', $userId)
            ->where('pokedex.visto', true)
            ->whereIn('pokemon.evolution_chain_id', $ids)
            ->distinct()
            ->pluck('pokemon.evolution_chain_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return new IntCollection(array_values($visibles));
    }

    public function tiposVistos(int $userId, IntCollection $pokemonIds): TiposPokemonCollection
    {
        $ids = $pokemonIds->all();

        if ($ids === []) {
            return new TiposPokemonCollection();
        }

        $filas = DB::table('pokemon_types')
            ->join('pokedex', 'pokedex.pokemon_id', '=', 'pokemon_types.pokemon_id')
            ->where('pokedex.user_id', $userId)
            ->where('pokedex.visto', true)
            ->whereIn('pokemon_types.pokemon_id', $ids)
            ->get(['pokemon_types.pokemon_id', 'pokemon_types.type']);

        /** @var array<int, array<int, DTOPokemonTipo>> $porPokemon */
        $porPokemon = [];

        foreach ($filas as $fila) {
            $tipo = TipoEnum::tryFrom((int) $fila->type);

            if ($tipo === null) {
                continue;
            }

            $porPokemon[$fila->pokemon_id][$tipo->value] = new DTOPokemonTipo(
                id: $tipo->value,
                name: $tipo->label(),
            );
        }

        ksort($porPokemon, SORT_NUMERIC);

        $coleccion = new TiposPokemonCollection();
        foreach ($porPokemon as $pokemonId => $tipos) {
            ksort($tipos, SORT_NUMERIC);
            $coleccion->add(new TiposPokemon(
                pokemonId: (int) $pokemonId,
                tipos: new ColeccionTiposFamilia(array_values($tipos)),
            ));
        }

        return $coleccion;
    }
}
