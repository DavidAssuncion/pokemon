<?php

declare(strict_types=1);

namespace Tests\Unit\Gimnasios;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Gimnasios\Domain\Collections\IntCollection;
use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;

class EquipoEtapaGimnasioTest extends TestCase
{
    #[Test]
    public function test_todos_concatena_vanguardia_y_retaguardia(): void
    {
        $equipo = new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([268, 266]),
            retaguardia: new IntCollection([900]),
        );

        $this->assertSame([268, 266, 900], $equipo->todos());
    }

    #[Test]
    public function test_preserva_orden_y_duplicados_en_todos(): void
    {
        $equipo = new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([338]),
            retaguardia: new IntCollection([464, 464]),
        );

        $this->assertSame([338, 464, 464], $equipo->todos());
    }

    #[Test]
    public function test_expone_vanguardia_y_retaguardia_como_colecciones(): void
    {
        $equipo = new EquipoEtapaGimnasio(
            vanguardia: new IntCollection([268, 266]),
            retaguardia: new IntCollection([900]),
        );

        $this->assertInstanceOf(IntCollection::class, $equipo->vanguardia);
        $this->assertInstanceOf(IntCollection::class, $equipo->retaguardia);
        $this->assertSame(2, $equipo->vanguardia->count());
        $this->assertSame(1, $equipo->retaguardia->count());
    }

    #[Test]
    public function test_rechaza_elementos_no_enteros(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new IntCollection(['a', 1]);
    }
}
