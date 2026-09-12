<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\RangoCapacidad;

class RangoCapacidadTest extends TestCase
{
    public function test_enum_es_backed_por_string(): void
    {
        $this->assertSame('novato', RangoCapacidad::NOVATO->value);
        $this->assertSame('competente', RangoCapacidad::COMPETENTE->value);
        $this->assertSame('experto', RangoCapacidad::EXPERTO->value);
        $this->assertSame('maestro', RangoCapacidad::MAESTRO->value);
    }

    public function test_nivel_devuelve_el_grado_del_rango(): void
    {
        $this->assertSame(0, RangoCapacidad::NOVATO->nivel());
        $this->assertSame(1, RangoCapacidad::COMPETENTE->nivel());
        $this->assertSame(2, RangoCapacidad::EXPERTO->nivel());
        $this->assertSame(3, RangoCapacidad::MAESTRO->nivel());
    }

    public function test_from_with_backed_value(): void
    {
        $this->assertSame(RangoCapacidad::MAESTRO, RangoCapacidad::from('maestro'));
        $this->assertSame(RangoCapacidad::NOVATO, RangoCapacidad::from('novato'));
    }
}
