<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use Illuminate\Support\Facades\DB;
use Src\Granjas\Domain\Ports\PokedexLecturaInterface;
use Src\Shared\Collections\IntCollection;
use Src\Shared\Domain\Exceptions\RecursoNoExiste;

/**
 * Guarda única de las acciones de granja: la familia debe estar avistada por
 * el jugador (salvo QA) y asignada al hábitat.
 *
 * Ambas comprobaciones lanzan la misma excepción, mensaje y estado (404) para
 * que una familia oculta, una no asignada y una inexistente sean
 * indistinguibles desde el cliente.
 */
final class FamiliaEnHabitat
{
    private const MENSAJE = 'Familia no disponible en este hábitat';

    public function __construct(
        private readonly PokedexLecturaInterface $pokedex,
    ) {
    }

    public function asegurar(
        int $userId,
        int $habitatId,
        int $evolutionChainId,
        bool $incluirNoAvistadas = false,
    ): void {
        if (! $incluirNoAvistadas) {
            $visibles = $this->pokedex->cadenasConAlgunVisto(
                $userId,
                new IntCollection([$evolutionChainId]),
            );

            if (! in_array($evolutionChainId, $visibles->all(), true)) {
                throw new RecursoNoExiste(self::MENSAJE);
            }
        }

        $existe = DB::table('pokemon_habitat')
            ->join('pokemon', 'pokemon.id', '=', 'pokemon_habitat.pokemon_id')
            ->where('pokemon_habitat.habitat_id', $habitatId)
            ->where('pokemon.evolution_chain_id', $evolutionChainId)
            ->exists();

        if (! $existe) {
            throw new RecursoNoExiste(self::MENSAJE);
        }
    }
}
