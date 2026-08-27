<?php

declare(strict_types=1);

namespace Src\Shared\Bus;

/**
 * Maneja un comando concreto. Cada handler se resuelve por convención:
 * `FooCommand` -> `FooHandler` en el mismo namespace.
 */
interface CommandHandler
{
    public function handle(Command $command): mixed;
}
