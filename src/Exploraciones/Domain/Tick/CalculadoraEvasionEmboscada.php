<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Tick;

use Src\Exploraciones\Domain\CapacidadesStats;

/**
 * Reglas puras de evasión de emboscadas.
 *
 * La detección MAESTRO evita automáticamente. En el resto de casos la tirada
 * solo se consume cuando la capacidad permite emboscadas (el orquestador
 * conserva ese orden para no alterar la secuencia aleatoria).
 */
final class CalculadoraEvasionEmboscada
{
    /**
     * ¿La detección automática evita la emboscada sin tirada?
     */
    public function evitaAutomaticamente(CapacidadesStats $capacidades, int $dificultad): bool
    {
        return $capacidades->deteccionAutoEvasion($dificultad);
    }

    /**
     * ¿La tirada [0,1) evita la emboscada?
     */
    public function evitaConTirada(float $tirada): bool
    {
        return $tirada < 0.5;
    }
}
