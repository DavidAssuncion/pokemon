<?php

declare(strict_types=1);

namespace Src\Habitats\Domain;

/**
 * Calcula el nivel mínimo de jugador para un spawn de hábitat.
 *
 * Reglas de producto:
 * - Iniciales (cualquier miembro de la familia y sus formas regionales): 35.
 * - Paradoja / prehistóricos: 55.
 * - Evolucionados: su nivel de evolución (`pokemon_evolution.minimum_level`).
 * - Resto: el mínimo del tier (1 → 1, 2 → 8, 3 → 20).
 *
 * El resultado es el máximo de las reglas aplicables, de modo que una regla
 * nunca rebaja el mínimo de otra.
 */
final class CalculadoraNivelMinimoSpawn
{
    /**
     * @param  array<int, int>  $minLevelPorTier
     * @param  list<int>  $starterChainIds
     * @param  list<int>  $starterSpeciesIds
     * @param  list<int>  $paradoxSpeciesIds
     * @param  array<int, int>  $speciesMinLevelOverrides
     */
    public function __construct(
        private readonly array $minLevelPorTier,
        private readonly int $starterMinLevel,
        private readonly array $starterChainIds,
        private readonly array $starterSpeciesIds,
        private readonly int $paradoxMinLevel = 55,
        private readonly array $paradoxSpeciesIds = [],
        private readonly array $speciesMinLevelOverrides = [],
    ) {
    }

    public function calcular(
        int $tier,
        int $evolutionChainId,
        int $speciesId,
        ?int $nivelEvolucion,
    ): int {
        // Overrides explícitos por especie (Slowpoke, Lapras…).
        if (isset($this->speciesMinLevelOverrides[$speciesId])) {
            return $this->speciesMinLevelOverrides[$speciesId];
        }

        // Los Paradoja no evolucionan y se desbloquean siempre a 55.
        if (in_array($speciesId, $this->paradoxSpeciesIds, true)) {
            return $this->paradoxMinLevel;
        }

        // Los iniciales (y sus formas regionales) se desbloquean siempre a 35,
        // por encima del nivel de evolución de cada etapa.
        if ($this->esStarter($evolutionChainId, $speciesId)) {
            return $this->starterMinLevel;
        }

        $minimo = $this->minLevelPorTier[$tier] ?? $this->minLevelPorTier[3] ?? 20;

        if ($nivelEvolucion !== null) {
            $minimo = max($minimo, $nivelEvolucion);
        }

        return $minimo;
    }

    public function esStarter(int $evolutionChainId, int $speciesId): bool
    {
        return in_array($evolutionChainId, $this->starterChainIds, true)
            || in_array($speciesId, $this->starterSpeciesIds, true);
    }

    public function esParadoja(int $speciesId): bool
    {
        return in_array($speciesId, $this->paradoxSpeciesIds, true);
    }
}
