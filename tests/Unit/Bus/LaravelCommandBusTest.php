<?php

declare(strict_types=1);

namespace Tests\Unit\Bus;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use RuntimeException;
use Src\Shared\Bus\CommandBus;
use Tests\TestCase;
use Tests\Unit\Bus\Stubs\EchoCommand;
use Tests\Unit\Bus\Stubs\EchoHandler;
use Tests\Unit\Bus\Stubs\FailingCommand;
use Tests\Unit\Bus\Stubs\FailingHandler;
use Tests\Unit\Bus\Stubs\SinHandlerCommand;

class LaravelCommandBusTest extends TestCase
{
    use RefreshDatabase;

    private function bus(): CommandBus
    {
        return app(CommandBus::class);
    }

    public function test_despacha_al_handler_correcto_por_convencion(): void
    {
        EchoHandler::$llamadas = 0;

        $resultado = $this->bus()->dispatch(new EchoCommand('mundo'));

        $this->assertSame('mundo', $resultado);
        $this->assertSame(1, EchoHandler::$llamadas);
    }

    public function test_handler_inexistente_lanza_logic_exception_clara(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No command handler found for');

        $this->bus()->dispatch(new SinHandlerCommand());
    }

    public function test_excepcion_del_handler_se_propaga(): void
    {
        FailingHandler::$llamadas = 0;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fallo forzado');

        $this->bus()->dispatch(new FailingCommand());
    }
}
