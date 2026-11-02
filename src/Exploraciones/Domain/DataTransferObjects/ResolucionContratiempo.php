<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\DataTransferObjects;

/**
 * Resultado de resolver un contratiempo de exploración.
 *
 * Sustituye el array `{resolucion, duration_loss}` que devolvía el dominio.
 * `toArray()` queda como frontera para el evento de exploración, que sigue
 * siendo un payload de presentación.
 */
final readonly class ResolucionContratiempo
{
    public function __construct(
        public string $resolucion,
        public int $durationLoss,
    ) {
    }

    /**
     * @return array{resolucion: string, duration_loss: int}
     */
    public function toArray(): array
    {
        return [
            'resolucion' => $this->resolucion,
            'duration_loss' => $this->durationLoss,
        ];
    }
}
