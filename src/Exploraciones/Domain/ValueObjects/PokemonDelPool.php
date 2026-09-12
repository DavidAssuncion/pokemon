<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\ValueObjects;

use Src\Shared\Tipos\TiposCollection;

/**
 * Value Object inmutable con un pokémon del pool de hábitat (RF-04). Los
 * campos de experiencia/cadena (baseExperience, evolutionChainId, speciesId)
 * son opcionales: solo los aporta la variante enriquecida del Estimador
 * de recompensas (RF-D) y nunca se emiten por defecto en la frontera.
 */
final readonly class PokemonDelPool
{
    public function __construct(
        public readonly int $id,
        public readonly int $captureRate,
        public readonly ?int $hatch,
        public readonly TiposCollection $tipos,
        public readonly ColeccionStatsDelPool $stats,
        public readonly int $baseExperience = 0,
        public readonly ?int $evolutionChainId = null,
        public readonly int $speciesId = 0,
    ) {
    }

    /**
     * Frontera — lectura del contrato del pool. tolera entradas del pool del
     * handler (id/capture_rate/hatch/tipos/stats) tanto de la variante
     * enriquecida del estimador (base_experience/evolution_chain_id/species_id).
     *
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArray(array $datos): self
    {
        return new self(
            id: (int) ($datos['id'] ?? 0),
            captureRate: (int) ($datos['capture_rate'] ?? 0),
            hatch: isset($datos['hatch']) ? (int) $datos['hatch'] : null,
            tipos: new TiposCollection($datos['tipos'] ?? []),
            stats: ColeccionStatsDelPool::desdeLista($datos['stats'] ?? []),
            baseExperience: (int) ($datos['base_experience'] ?? 0),
            evolutionChainId: isset($datos['evolution_chain_id']) ? (int) $datos['evolution_chain_id'] : null,
            speciesId: (int) ($datos['species_id'] ?? 0),
        );
    }

    /**
     * Peso del pokémon en el pool ponderado: capture_rate / hatch. Los hatch
     * nulos o cero se tratan como 1 y un capture_rate <= 0 excluye la entrada.
     */
    public function peso(): float
    {
        if ($this->captureRate <= 0) {
            return 0.0;
        }

        $divisor = ($this->hatch === null || $this->hatch <= 0) ? 1 : $this->hatch;

        return $this->captureRate / $divisor;
    }

    /**
     * Frontera — shape exacto del contrato previo del pool (sin los campos
     * opcionales del estimador salvo que se hayan cargado).
     *
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        $datos = [
            'id' => $this->id,
            'capture_rate' => $this->captureRate,
            'hatch' => $this->hatch,
            'tipos' => $this->tipos->toList(),
            'stats' => $this->stats->aLista(),
        ];

        if ($this->baseExperience !== 0) {
            $datos['base_experience'] = $this->baseExperience;
        }

        if ($this->evolutionChainId !== null) {
            $datos['evolution_chain_id'] = $this->evolutionChainId;
        }

        if ($this->speciesId !== 0) {
            $datos['species_id'] = $this->speciesId;
        }

        return $datos;
    }
}
