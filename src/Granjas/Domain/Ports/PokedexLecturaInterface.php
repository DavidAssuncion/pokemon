<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\Ports;

use Src\Granjas\Domain\Collections\TiposPokemonCollection;
use Src\Shared\Collections\IntCollection;

/**
 * Lectura del conocimiento del jugador sobre pokémon: qué ha avistado, qué
 * cadenas evolutivas tiene visibles y los tipos de esos pokémon.
 *
 * No depende de Auth: el `userId` es explícito para que las consultas no
 * dependan del scope implícito del modelo `Pokedex`.
 */
interface PokedexLecturaInterface
{
    /**
     * Subconjunto de $candidatos con `pokedex.visto = true` para el usuario.
     */
    public function vistos(int $userId, IntCollection $candidatos): IntCollection;

    /**
     * Cadenas evolutivas (de $cadenas) con al menos un miembro visto.
     */
    public function cadenasConAlgunVisto(int $userId, IntCollection $cadenas): IntCollection;

    /**
     * Tipos de los pokémon vistos por el usuario, restringido a $pokemonIds.
     */
    public function tiposVistos(int $userId, IntCollection $pokemonIds): TiposPokemonCollection;
}
