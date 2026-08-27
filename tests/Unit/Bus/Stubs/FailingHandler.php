<?php

declare(strict_types=1);

namespace Tests\Unit\Bus\Stubs;

use RuntimeException;
use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandHandler;

final class FailingHandler implements CommandHandler
{
    public static int $llamadas = 0;

    public function handle(Command $command): mixed
    {
        self::$llamadas++;

        throw new RuntimeException('fallo forzado');
    }
}
