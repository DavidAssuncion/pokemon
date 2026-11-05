<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use Illuminate\Support\Facades\DB;

/**
 * Comprueba si una especie está asignada al hábitat de la expedición
 * (`pokemon_habitat`, unique por `pokemon_id` + `habitat_id`).
 */
final class EsDelHabitat
{
    public function handle(int $pokemonId, int $habitatId): bool
    {
        return DB::table('pokemon_habitat')
            ->where('pokemon_id', $pokemonId)
            ->where('habitat_id', $habitatId)
            ->exists();
    }
}
