<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\ActualizarPokedexJob;

/**
 * Despacha el evento AVISTADO de la pokedex por cada pokémon derrotado/avistado.
 * Compartido por OtorgarRecompensasRuta, OtorgarRecompensasEntrenador y
 * FinalizarExploracionHandler (antes duplicado en cada flujo).
 */
final class AvistamientosPokedex
{
    /**
     * @param  list<int>  $pokemonIds
     */
    public static function despachar(array $pokemonIds, int $userId): void
    {
        foreach (array_unique($pokemonIds) as $pokemonId) {
            ActualizarPokedexJob::dispatch($userId, $pokemonId, 'AVISTADO');
        }
    }
}
