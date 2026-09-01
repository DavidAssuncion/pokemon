<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

final class GimnasioCompletado extends ViolacionReglaNegocio
{
    public function __construct()
    {
        parent::__construct('Ya has completado este gimnasio.');
    }
}
