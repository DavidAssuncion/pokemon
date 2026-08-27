<?php

declare(strict_types=1);

namespace App\Bus;

use Illuminate\Contracts\Foundation\Application;
use LogicException;
use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandBus;
use Src\Shared\Bus\CommandHandler;
use Src\Shared\Bus\UnitOfWork;

/**
 * Bus por convención: `App\...\FooCommand` -> `FooHandler` en el mismo
 * namespace. Todo comando se ejecuta dentro de UnitOfWork::transaction()
 * (rollback automático si el handler lanza).
 */
final class LaravelCommandBus implements CommandBus
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Application $app,
    ) {
    }

    public function dispatch(Command $command): mixed
    {
        $handler = $this->resolveHandler($command);

        return $this->unitOfWork->transaction(fn (): mixed => $handler->handle($command));
    }

    private function resolveHandler(Command $command): CommandHandler
    {
        $handlerClass = str_replace('Command', 'Handler', $command::class);

        if (! class_exists($handlerClass)) {
            throw new LogicException(sprintf('No command handler found for %s (expected %s).', $command::class, $handlerClass));
        }

        $handler = $this->app->make($handlerClass);

        if (! $handler instanceof CommandHandler) {
            throw new LogicException(sprintf('Command handler %s must implement %s.', $handlerClass, CommandHandler::class));
        }

        return $handler;
    }
}
