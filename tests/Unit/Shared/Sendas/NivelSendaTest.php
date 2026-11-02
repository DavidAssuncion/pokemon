<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Sendas;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Sendas\NivelSenda;

class NivelSendaTest extends TestCase
{
    #[Test]
    public function test_expone_los_cinco_niveles(): void
    {
        $this->assertSame(
            [1, 2, 3, 4, 5],
            array_map(static fn (NivelSenda $nivel): int => $nivel->value, NivelSenda::cases()),
        );
    }

    /**
     * @return array<string, array{0: int, 1: NivelSenda}>
     */
    public static function nivelesConClamp(): array
    {
        return [
            'negativo' => [-10, NivelSenda::N1],
            'cero' => [0, NivelSenda::N1],
            'uno' => [1, NivelSenda::N1],
            'dos' => [2, NivelSenda::N2],
            'tres' => [3, NivelSenda::N3],
            'cuatro' => [4, NivelSenda::N4],
            'cinco' => [5, NivelSenda::N5],
            'superior' => [99, NivelSenda::N5],
        ];
    }

    #[Test]
    #[DataProvider('nivelesConClamp')]
    public function test_desde_int_acota_entre_uno_y_cinco(int $entrada, NivelSenda $esperado): void
    {
        $this->assertSame($esperado, NivelSenda::desdeInt($entrada));
    }

    /**
     * @return array<string, array{0: NivelSenda, 1: int}>
     */
    public static function nivelesVisibles(): array
    {
        return [
            'N1' => [NivelSenda::N1, 1],
            'N2' => [NivelSenda::N2, 2],
            'N3' => [NivelSenda::N3, 3],
            'N4' => [NivelSenda::N4, 3],
            'N5' => [NivelSenda::N5, 3],
        ];
    }

    #[Test]
    #[DataProvider('nivelesVisibles')]
    public function test_visible_comprime_los_niveles_cuatro_y_cinco(NivelSenda $nivel, int $esperado): void
    {
        $this->assertSame($esperado, $nivel->visible());
    }

    #[Test]
    public function test_solo_los_niveles_cuatro_y_cinco_tienen_pasivos(): void
    {
        $this->assertFalse(NivelSenda::N1->tienePasivos());
        $this->assertFalse(NivelSenda::N2->tienePasivos());
        $this->assertFalse(NivelSenda::N3->tienePasivos());
        $this->assertTrue(NivelSenda::N4->tienePasivos());
        $this->assertTrue(NivelSenda::N5->tienePasivos());
    }
}
