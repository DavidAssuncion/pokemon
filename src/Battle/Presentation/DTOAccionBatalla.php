<?php

declare(strict_types=1);

namespace Src\Battle\Presentation;

use Livewire\Wireable;

/**
 * DTO de presentación para una acción de batalla pendiente.
 * Reemplaza el array asociativo pendingAction.
 * Implementa Wireable para viajar a través de Livewire.
 */
class DTOAccionBatalla implements Wireable
{
    public function __construct(
        public readonly string $type,
        public readonly string $actorId,
        public readonly string $defenderId,
        public readonly string $attackerNombre,
        public readonly DTOMovimientoBatalla $move,
    ) {
    }

    public function toLivewire(): array
    {
        return [
            'type' => $this->type,
            'actorId' => $this->actorId,
            'defenderId' => $this->defenderId,
            'attackerNombre' => $this->attackerNombre,
            'move' => $this->move->toLivewire(),
        ];
    }

    public static function fromLivewire($value): static
    {
        return new static(
            type: $value['type'] ?? 'attack',
            actorId: $value['actorId'],
            defenderId: $value['defenderId'],
            attackerNombre: $value['attackerNombre'] ?? '',
            move: DTOMovimientoBatalla::fromLivewire($value['move']),
        );
    }
}
