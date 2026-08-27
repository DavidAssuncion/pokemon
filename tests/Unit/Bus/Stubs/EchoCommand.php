<?php

declare(strict_types=1);

namespace Tests\Unit\Bus\Stubs;

use Src\Shared\Bus\Command;

final class EchoCommand implements Command
{
    public function __construct(
        public readonly string $message = 'hola'
    ) {
    }
}
