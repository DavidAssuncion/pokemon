<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Pokemon;
use Illuminate\Support\Collection;

/**
 * Carga pokémon por ids (con stats y types) para las recompensas de combate y
 * exploración. Compartido por OtorgarRecompensasRuta, OtorgarRecompensasEntrenador
 * y FinalizarExploracionHandler (antes duplicado en cada flujo).
 */
final class CargadorPokemonsDerrotados
{
    /**
     * @param  list<int>  $ids
     * @return Collection<int, Pokemon> keyed por id, con stats y types.
     */
    public static function porIds(array $ids): Collection
    {
        $idsUnicos = collect($ids)->unique()->values();

        if ($idsUnicos->isEmpty()) {
            return new Collection();
        }

        $query = Pokemon::query()->with('stats', 'types');
        $query->getQuery()->whereIn('id', $idsUnicos);

        return $query->get()->keyBy('id');
    }
}
