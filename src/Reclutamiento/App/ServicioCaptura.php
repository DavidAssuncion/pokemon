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
    public function procesarCapturas(array $pokemonDefeatedIds, int $userId): void
    {
        foreach ($pokemonDefeatedIds as $pokemonId) {
            $pokemon = Pokemon::find($pokemonId);
            if (! $pokemon) {
                continue;
            }

            // Mark as sighted in pokedex
            ActualizarPokedexJob::dispatch($userId, $pokemonId, 'AVISTADO');

            // Attempt capture based on capture_rate
            $captureChance = min($pokemon->capture_rate ?? 45, 45) / 255;
            CapturarPokemonJob::dispatch($userId, $pokemonId, $captureChance);
        }
    }
}
