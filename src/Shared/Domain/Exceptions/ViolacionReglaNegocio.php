<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

class ViolacionReglaNegocio extends DominioExcepcion
{
    public function __construct(string $message = 'Violación de regla de negocio')
    {
        parent::__construct($message, 422);
    }
}
