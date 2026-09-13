<?php

declare(strict_types=1);

namespace Tests\Unit\CombateEntrenadores;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\CombateEntrenadores\Domain\ClasificadorPosicion;
use Src\Pokemon\Domain\Stats\DatosStats;

class ClasificadorPosicionTest extends TestCase
{
    private ClasificadorPosicion $clasificador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clasificador = new ClasificadorPosicion();
    }

    #[Test]
    public function es_defensivo_cuando_defensa_no_superada_por_ofensiva(): void
    {
        // ofensiva = 100 + 100 = 200; defensiva = 80 + 70 + 50 = 200 → empate → defensivo
        $this->assertTrue(
            $this->clasificador->esDefensivo(
                new DatosStats(hp: 50, atk: 100, def: 80, spAtk: 40, spDef: 70, speed: 100)
            )
        );
    }

    #[Test]
    public function no_es_defensivo_cuando_ofensiva_supera_defensa(): void
    {
        // ofensiva = 150 + 130 = 280; defensiva = 80 + 70 + 90 = 240 → ofensivo
        $this->assertFalse(
            $this->clasificador->esDefensivo(
                new DatosStats(hp: 90, atk: 150, def: 80, spAtk: 40, spDef: 70, speed: 130)
            )
        );
    }

    #[Test]
    public function usa_formula_nueva_atk_speed_vs_def_sdef_hp(): void
    {
        // Antigua fórmula: atk+spAtk=110 vs def+spDef=100 → ofensivo (retaguardia)
        // Nueva fórmula: atk+speed=200 vs def+spDef+hp=200 → empate → defensivo (vanguardia)
        $this->assertTrue(
            $this->clasificador->esDefensivo(
                new DatosStats(hp: 100, atk: 100, def: 50, spAtk: 10, spDef: 50, speed: 100)
            )
        );
    }
}
