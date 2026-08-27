<?php

declare(strict_types=1);

namespace Src\Reclutamiento\App;

use App\Jobs\ActualizarPokedexJob;
use App\Jobs\CapturarPokemonJob;
use App\Models\Pokemon;

class ServicioCaptura
{
    /**
     * Process captures after a battle.
     *
     * @param int[] $pokemonDefeatedIds - IDs of defeated pokemon
     */
    public function procesarCapturas(array $pokemonDefeatedIds): void
    {
        foreach ($pokemonDefeatedIds as $pokemonId) {
            $pokemon = Pokemon::find($pokemonId);
            if (! $pokemon) {
                continue;
            }

            // Mark as sighted in pokedex
            ActualizarPokedexJob::dispatch($pokemonId, 'AVISTADO');

            // Attempt capture based on capture_rate
            $captureChance = ($pokemon->capture_rate ?? 45) / 255;
            CapturarPokemonJob::dispatch($pokemonId, $captureChance);
        }
    }
}
