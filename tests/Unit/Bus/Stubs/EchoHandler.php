<?php

declare(strict_types=1);

namespace Tests\Unit\Bus\Stubs;

use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandHandler;

final class EchoHandler implements CommandHandler
{
    public static int $llamadas = 0;

    public function handle(Command $command): mixed
    {
        if (! $command instanceof EchoCommand) {
            throw new \LogicException('EchoHandler requires an EchoCommand.');
        }

        self::$llamadas++;

        return $command->message;
    }
}
