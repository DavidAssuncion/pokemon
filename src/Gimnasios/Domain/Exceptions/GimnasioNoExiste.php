<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\RecursoNoExiste;

final class GimnasioNoExiste extends RecursoNoExiste
{
    public function __construct()
    {
        parent::__construct('El gimnasio solicitado no existe.');
    }
}
