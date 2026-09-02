<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CapacidadesStats;

class CapacidadesStatsTest extends TestCase
{
    private CapacidadesStats $stats;

    protected function setUp(): void
    {
        // hp=100, atk=80, def=70, spAtk=90, spDef=60, speed=50, nivelPok=10, nivelEntr=5
        $this->stats = new CapacidadesStats(
            hp: 100,
            atk: 80,
            def: 70,
            spAtk: 90,
            spDef: 60,
            speed: 50,
            nivelPokemon: 10,
            nivelEntrenador: 5,
        );
    }

    #[Test]
    public function test_combate_formula(): void
    {
        // 0.25*80 + 0.25*90 + 0.25*70 + 0.25*60 + 10 + 5
        // = 20 + 22.5 + 17.5 + 15 + 10 + 5 = 90
        $this->assertSame(90.0, $this->stats->combate());
    }

    #[Test]
    public function test_deteccion_formula(): void
    {
        // 0.60*50 + 0.40*60 + 10 + 5
        // = 30 + 24 + 10 + 5 = 69
        $this->assertSame(69.0, $this->stats->deteccion());
    }

    #[Test]
    public function test_recoleccion_formula(): void
    {
        // 0.25*60 + 0.25*50 + 0.25*100 + 0.25*70 + 10 + 5
        // = 15 + 12.5 + 25 + 17.5 + 10 + 5 = 85
        $this->assertSame(85.0, $this->stats->recoleccion());
    }

    #[Test]
    public function test_supervivencia_formula(): void
    {
        // 0.33*100 + 0.33*70 + 0.33*60 + 10 + 5
        // = 33 + 23.1 + 19.8 + 10 + 5 = 90.9
        $this->assertSame(90.9, $this->stats->supervivencia());
    }

    #[Test]
    public function test_exploracion_formula(): void
    {
        // 0.40*50 + 0.20*60 + 0.20*70 + 0.20*supervivencia() + 10 + 5
        // supervivencia = 90.9
        // = 20 + 12 + 14 + 18.18 + 10 + 5 = 79.18
        $this->assertSame(79.18, $this->stats->exploracion());
    }

    #[Test]
    public function test_movilidad_formula(): void
    {
        // 1.00*50 + 10 + 5 = 65
        $this->assertSame(65.0, $this->stats->movilidad());
    }

    #[Test]
    public function test_todas_devuelve_array_con_6_claves(): void
    {
        $todas = $this->stats->todas();
        $this->assertCount(6, $todas);
        $this->assertArrayHasKey('combate', $todas);
        $this->assertArrayHasKey('deteccion', $todas);
        $this->assertArrayHasKey('recoleccion', $todas);
        $this->assertArrayHasKey('supervivencia', $todas);
        $this->assertArrayHasKey('exploracion', $todas);
        $this->assertArrayHasKey('movilidad', $todas);
    }

    #[Test]
    public function test_todas_valores_coinciden_con_metodos_individuales(): void
    {
        $todas = $this->stats->todas();
        $this->assertSame($this->stats->combate(), $todas['combate']);
        $this->assertSame($this->stats->deteccion(), $todas['deteccion']);
        $this->assertSame($this->stats->recoleccion(), $todas['recoleccion']);
        $this->assertSame($this->stats->supervivencia(), $todas['supervivencia']);
        $this->assertSame($this->stats->exploracion(), $todas['exploracion']);
        $this->assertSame($this->stats->movilidad(), $todas['movilidad']);
    }

    #[Test]
    public function test_stats_cero(): void
    {
        $cero = new CapacidadesStats(0, 0, 0, 0, 0, 0, 0, 0);
        $this->assertSame(0.0, $cero->combate());
        $this->assertSame(0.0, $cero->deteccion());
        $this->assertSame(0.0, $cero->recoleccion());
        $this->assertSame(0.0, $cero->supervivencia());
        $this->assertSame(0.0, $cero->exploracion());
        $this->assertSame(0.0, $cero->movilidad());
    }

    #[Test]
    public function test_solo_niveles_sin_stats(): void
    {
        $soloNiveles = new CapacidadesStats(0, 0, 0, 0, 0, 0, 10, 20);
        $this->assertSame(30.0, $soloNiveles->combate()); // 0 + 10 + 20
        $this->assertSame(30.0, $soloNiveles->deteccion());
        $this->assertSame(30.0, $soloNiveles->recoleccion());
        $this->assertSame(30.0, $soloNiveles->supervivencia());
        // exploracion incluye 0.20 * supervivencia() → 0 + 0.2*30 + 30 = 36
        $this->assertSame(36.0, $soloNiveles->exploracion());
        $this->assertSame(30.0, $soloNiveles->movilidad());
    }
}
