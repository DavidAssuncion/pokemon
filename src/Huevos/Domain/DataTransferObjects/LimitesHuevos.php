<?php

declare(strict_types=1);

namespace Src\Huevos\Domain\DataTransferObjects;

/**
 * Límites y estado global de la incubación del jugador.
 */
final readonly class LimitesHuevos
{
    public function __construct(
        public readonly int $maxGestacion,
        public readonly int $maxIncubando,
        public readonly bool $gestacionActiva,
        public readonly bool $incubacionActiva,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato JSON previo.
     *
     * @return array{
     *     max_gestacion: int,
     *     max_incubando: int,
     *     gestacion_activa: bool,
     *     incubacion_activa: bool,
     * }
     */
    public function toArray(): array
    {
        return [
            'max_gestacion' => $this->maxGestacion,
            'max_incubando' => $this->maxIncubando,
            'gestacion_activa' => $this->gestacionActiva,
            'incubacion_activa' => $this->incubacionActiva,
        ];
    }
}
