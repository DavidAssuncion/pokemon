<?php

declare(strict_types=1);

namespace Src\Shared\Bus;

/**
 * Despacha comandos a sus handlers. En v1 todo comando se ejecuta
 * dentro de una transacción (UnitOfWork) con rollback automático.
 */
interface CommandBus
{
    public function dispatch(Command $command): mixed;
}
