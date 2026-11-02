<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\ValueObjects\EventoExploracion;

final class EventoExploracionTest extends TestCase
{
    public function test_round_trip_conserva_las_cinco_claves_de_pv_incluido_cero(): void
    {
        $evento = EventoExploracion::desdeArray([
            'tipo' => 'encuentro',
            'resolucion' => 'derrota',
            'hp_inicial' => 20.0,
            'hp_max' => 39.0,
            'rival_hp_inicial' => 32.0,
            'rival_hp_final' => 0.0,
            'rival_hp_max' => 32.0,
        ]);

        $datos = $evento->aArray();

        $this->assertSame(20.0, $datos['hp_inicial']);
        $this->assertSame(39.0, $datos['hp_max']);
        $this->assertSame(32.0, $datos['rival_hp_inicial']);
        $this->assertSame(0.0, $datos['rival_hp_final']);
        $this->assertSame(32.0, $datos['rival_hp_max']);
    }
}
