<?php

declare(strict_types=1);

namespace Src\Mazmorras\Domain\DataTransferObjects;

use Src\Mazmorras\Domain\Collections\PisoMazmorraCollection;

/**
 * Resultado de ObtenerPisosMazmorra::obtener(): los pisos alcanzables de la
 * mazmorra de un hábitat con su estado.
 *
 * Sustituye el array {pisos: list<array{...}>} (deuda F6). toArray() solo se
 * usa en la frontera (JSON / vista).
 */
final readonly class ResultadoPisosMazmorra
{
    public function __construct(
        public readonly PisoMazmorraCollection $pisos,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato previo (clave `pisos`).
     *
     * @return array{pisos: list<array{piso: int, nombre: string, estado: string, cooldown_hasta: string|null}>}
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return [
            'pisos' => $this->pisos->toArray(),
        ];
    }
}
