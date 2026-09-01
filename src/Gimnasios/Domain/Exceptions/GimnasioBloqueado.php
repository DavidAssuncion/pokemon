<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

final class GimnasioBloqueado extends ViolacionReglaNegocio
{
    public function __construct(int $nivelMinimo)
    {
        parent::__construct("Aún no tienes nivel suficiente. Necesitas nivel {$nivelMinimo}.");
    }
}
