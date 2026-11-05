<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Shared\Domain\Items\GrupoBaya;

/**
 * Baya disponible en el inventario del jugador para plantar en un nido.
 */
final readonly class BayaGranja
{
    public function __construct(
        public string $clave,
        public string $nombre,
        public string $icono,
        public int $cantidad,
        public GrupoBaya $grupo,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'icono' => $this->icono,
            'cantidad' => $this->cantidad,
            'grupo' => $this->grupo->value,
            'grupo_nombre' => $this->grupo->label(),
        ];
    }
}
