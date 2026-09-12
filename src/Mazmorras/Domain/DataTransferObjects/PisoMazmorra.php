<?php

declare(strict_types=1);

namespace Src\Mazmorras\Domain\DataTransferObjects;

/**
 * Un piso alcanzable de la mazmorra de un hábitat con su estado
 * (ganado/disponible/cooldown).
 *
 * Sustituye el array {piso, nombre, estado, cooldown_hasta} que devolvía
 * ObtenerPisosMazmorra::obtener() (deuda F6).
 */
final readonly class PisoMazmorra
{
    public function __construct(
        public readonly int $piso,
        public readonly string $nombre,
        public readonly string $estado,
        public readonly ?string $cooldownHasta,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato previo (clave snake_case).
     *
     * @return array{piso: int, nombre: string, estado: string, cooldown_hasta: string|null}
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return [
            'piso' => $this->piso,
            'nombre' => $this->nombre,
            'estado' => $this->estado,
            'cooldown_hasta' => $this->cooldownHasta,
        ];
    }
}
