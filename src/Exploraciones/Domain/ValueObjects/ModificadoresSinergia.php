<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\ValueObjects;

/**
 * Modificadores que aplica una sinergia de equipo.
 */
final readonly class ModificadoresSinergia
{
    public function __construct(
        public readonly string $nombre,
        public readonly int $bonusCapacidad = 0,
        public readonly int $bonusResolucion = 0,
        public readonly float $multiplicadorCaramelos = 1.0,
        public readonly float $multiplicadorHuidas = 1.0,
        public readonly float $multiplicadorRetirada = 1.0,
        public readonly bool $detectaEmboscadas = false,
        public readonly bool $reduccionTiempo = false,
    ) {
    }

    /**
     * Frontera — shape consumido por el controlador/vista.
     *
     * @return array{
     *     nombre: string,
     *     bonusCapacidad: int,
     *     bonusResolucion: int,
     *     multiplicadorCaramelos: float,
     *     multiplicadorHuidas: float,
     *     multiplicadorRetirada: float,
     *     detectaEmboscadas: bool,
     *     reduccionTiempo: bool,
     * }
     */
    public function toArray(): array
    {
        return [
            'nombre' => $this->nombre,
            'bonusCapacidad' => $this->bonusCapacidad,
            'bonusResolucion' => $this->bonusResolucion,
            'multiplicadorCaramelos' => $this->multiplicadorCaramelos,
            'multiplicadorHuidas' => $this->multiplicadorHuidas,
            'multiplicadorRetirada' => $this->multiplicadorRetirada,
            'detectaEmboscadas' => $this->detectaEmboscadas,
            'reduccionTiempo' => $this->reduccionTiempo,
        ];
    }
}
