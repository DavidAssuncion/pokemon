<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Recompensas;

/**
 * Caramelos de familia (cadena evolutiva) obtenidos al derrotar pokémon.
 */
final class RecompensaFamilia
{
    public function __construct(
        public readonly int $evolutionChainId,
        public readonly int $cantidad,
    ) {
    }
}
