<?php

declare(strict_types=1);

namespace Src\Huevos\Domain\DataTransferObjects;

use Src\Huevos\Domain\Collections\HuevoDisponibleCollection;
use Src\Huevos\Domain\Collections\HuevoResumenCollection;

/**
 * Contrato de GET /api/huevos: huevos activos, familias disponibles y límites.
 */
final readonly class ResumenHuevos
{
    public function __construct(
        public readonly HuevoResumenCollection $huevos,
        public readonly HuevoDisponibleCollection $disponibles,
        public readonly LimitesHuevos $limites,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato JSON previo.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'huevos' => $this->huevos->toArray(),
            'disponibles' => $this->disponibles->toArray(),
            'limites' => $this->limites->toArray(),
        ];
    }
}
