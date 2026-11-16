<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Ports;

/**
 * Puerto de consulta del factor de aparición de un pokémon salvaje.
 *
 * Permite que los módulos que generan encuentros salvajes apliquen eventos de
 * aparición sin depender del módulo que los implementa. Un factor de 1.0
 * significa que no hay ningún evento aplicable al par hábitat/familia.
 */
interface FactorAparicion
{
    /**
     * Factor multiplicativo de aparición para un hábitat y una cadena evolutiva.
     */
    public function factor(int $habitatId, ?int $evolutionChainId): float;
}
