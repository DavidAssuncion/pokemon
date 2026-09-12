<?php

declare(strict_types=1);

namespace Src\Exploraciones\Presentation;

/**
 * Conteo de incidentes de una expedición: encuentros, victorias,
 * huidas, emboscadas y contratiempos.
 *
 * Generado por FinalizarExploracionHandler::incidentes() y serializado
 * a eventos['resultado']['incidentes'].
 */
final class DTOIncidentesExploracion
{
    public function __construct(
        public readonly int $encuentros,
        public readonly int $victorias,
        public readonly int $huidas,
        public readonly int $emboscadas,
        public readonly int $contratiempos,
    ) {
    }

    /**
     * @return array{encuentros: int, victorias: int, huidas: int, emboscadas: int, contratiempos: int}
     */
    public function toArray(): array
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
