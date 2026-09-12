<?php

declare(strict_types=1);

namespace Src\Mazmorras\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

final class PisoNoDisponible extends ViolacionReglaNegocio
{
    public function __construct(string $mensaje)
    {
        parent::__construct($mensaje);
    }
}
