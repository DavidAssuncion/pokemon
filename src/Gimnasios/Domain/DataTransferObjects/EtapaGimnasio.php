<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\DataTransferObjects;

/**
 * Una etapa del detalle de gimnasio (etapa 1-4 con su nombre).
 */
final readonly class EtapaGimnasio
{
    public function __construct(
        public readonly int $etapa,
        public readonly string $nombre,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato previo.
     *
     * @return array{etapa: int, nombre: string}
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return [
            'etapa' => $this->etapa,
            'nombre' => $this->nombre,
        ];
    }
}
