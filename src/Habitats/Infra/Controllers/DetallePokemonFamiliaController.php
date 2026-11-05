<?php

declare(strict_types=1);

namespace Src\Habitats\Infra\Controllers;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\JsonResponse;
use Src\Habitats\Infra\Queries\DetallePokemonFamiliaQuery;

/**
 * Detalle completo de un Pokémon dentro de una familia de hábitat.
 */
final class DetallePokemonFamiliaController
{
    public function __construct(
        private readonly DetallePokemonFamiliaQuery $query,
        private readonly Guard $auth,
    ) {
    }

    public function show(int $habitat, int $chain, int $pokemon): JsonResponse
    {
        return response()->json(
            $this->query->obtener($habitat, $chain, $pokemon, (int) $this->auth->id()),
        );
    }
}
