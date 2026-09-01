<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

final class EtapaNoDisponible extends ViolacionReglaNegocio
{
    public function __construct()
    {
        parent::__construct('La etapa solicitada no está disponible para el combate.');
    }
}
