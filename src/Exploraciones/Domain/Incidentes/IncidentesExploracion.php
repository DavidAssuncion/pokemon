<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Incidentes;

/**
 * Conteo de incidentes de una expedición.
 */
final readonly class IncidentesExploracion
{
    public function __construct(
        public int $encuentros,
        public int $victorias,
        public int $huidas,
        public int $emboscadas,
        public int $contratiempos,
    ) {
    }

    /**
     * @return array{encuentros: int, victorias: int, huidas: int, emboscadas: int, contratiempos: int}
     */
    public function aArray(): array
    {
        return [
            'encuentros' => $this->encuentros,
            'victorias' => $this->victorias,
            'huidas' => $this->huidas,
            'emboscadas' => $this->emboscadas,
            'contratiempos' => $this->contratiempos,
        ];
    }
}
