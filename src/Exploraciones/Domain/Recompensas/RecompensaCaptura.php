<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Recompensas;

/**
 * Captura de un pokémon salvaje: N ejemplares de la especie capturados.
 * `esShiny` distingue el slot shiny directo (O3) y se propaga al modal de ruta.
 */
final class RecompensaCaptura
{
    public function __construct(
        public readonly int $pokemonId,
        public readonly int $cantidad,
        public readonly bool $esShiny = false,
    ) {
    }
}
