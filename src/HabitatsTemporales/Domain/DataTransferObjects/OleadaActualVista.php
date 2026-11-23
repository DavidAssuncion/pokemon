<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\DataTransferObjects;

use Src\HabitatsTemporales\Domain\Enums\EstadoOleada;

/**
 * Oleada actual visible de una facción. Las oleadas futuras no se exponen.
 */
final readonly class OleadaActualVista
{
    public function __construct(
        public int $numero,
        public EstadoOleada $estado,
        public ?string $motivoBloqueo,
    ) {
    }

    /**
     * @return array{numero: int, estado: string, motivo_bloqueo: string|null}
     */
    public function toArray(): array
    {
        return [
            'numero' => $this->numero,
            'estado' => $this->estado->value,
            'motivo_bloqueo' => $this->motivoBloqueo,
        ];
    }
}
