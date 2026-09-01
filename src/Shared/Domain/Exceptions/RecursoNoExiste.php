<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

class RecursoNoExiste extends DominioExcepcion
{
    public function __construct(string $message = 'El recurso no existe.')
    {
        parent::__construct($message, 404);
    }
}
