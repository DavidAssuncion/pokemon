<?php

declare(strict_types=1);

namespace Src\Battle\Domain\DataTransferObjects;

use Src\Battle\Domain\AccionObjeto;

/**
 * Acción de objeto pendiente dentro del agregado de batalla.
 */
final readonly class DTOAccionObjeto
{
    public function __construct(
        public string $actorId,
        public string $objetivoId,
        public string $clave,
    ) {
    }

    public function toDomain(): AccionObjeto
    {
        return new AccionObjeto(
            actorId: $this->actorId,
            objetivoId: $this->objetivoId,
            clave: $this->clave,
        );
    }
}
