<?php

declare(strict_types=1);

namespace Tests\Unit\Gimnasios;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Src\Shared\Domain\EscaladorNivelRival;
use Tests\TestCase;

class EscaladorNivelRivalTest extends TestCase
{
    #[Test]
    #[DataProvider('casosEscalado')]
    public function test_escala_correctamente(int $nivelMinimo, int $nivelJugador, int $esperado): void
    {
        $escalador = new EscaladorNivelRival();
        $this->assertSame($esperado, $escalador->escalar($nivelMinimo, $nivelJugador));
    }

    /** @return array<string, array{int, int, int}> */
    public static function casosEscalado(): array
    {
        return [
            'nivel_jugador_igual_al_minimo' => [10, 10, 10],
            'diferencia_par' => [10, 20, 15],
            'diferencia_impar_floor' => [10, 21, 15],
            'diferencia_grande' => [10, 50, 30],
            'nivel_jugador_inferior_clamp' => [10, 5, 10],
            'nivel_jugador_muy_inferior_clamp' => [31, 1, 31],
            'nivel_min_cero' => [0, 10, 5],
            'nivel_altos' => [25, 40, 32],
            'nivel_min_15_diferencia_1' => [15, 16, 15],
            'nivel_min_31_jugador_31' => [31, 31, 31],
        ];
    }
}
