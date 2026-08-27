<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Recompensas;

/**
 * Caramelos EV de un stat concreto obtenidos al derrotar pokémon.
 */
final class RecompensaEv
{
    public function __construct(
        public readonly int $stat,
        public readonly int $cantidad,
    ) {
    }
}
