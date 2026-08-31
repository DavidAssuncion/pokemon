<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

final class ViolacionReglaNegocio extends DominioExcepcion
{
    public function __construct(string $message = 'Violación de regla de negocio')
    {
        parent::__construct($message, 422);
    }
}
