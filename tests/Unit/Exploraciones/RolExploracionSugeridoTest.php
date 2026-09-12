<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CapacidadesStats;
use Src\Exploraciones\Domain\RolExploracion;

class RolExploracionSugeridoTest extends TestCase
{
    /** El rol sugerido debe ser COMBATIENTE cuando la capacidad de combate domina. */
    #[Test]
    public function sugerido_es_combatiente_cuando_domina_el_combate(): void
    {
        $stats = new CapacidadesStats(
            hp: 20,
            atk: 90,
            def: 30,
            spAtk: 95,
            spDef: 25,
            speed: 30,
            nivelPokemon: 0,
            nivelEntrenador: 0,
        );

        $this->assertSame(
            RolExploracion::COMBATIENTE,
            RolExploracion::sugeridoPara($stats),
        );
    }

    /** El rol sugerido debe ser RECOLECTOR cuando la recolección domina. */
    #[Test]
    public function sugerido_es_recolector_cuando_domina_la_recoleccion(): void
    {
        $stats = new CapacidadesStats(
            hp: 100,
            atk: 20,
            def: 100,
            spAtk: 20,
            spDef: 100,
            speed: 100,
            nivelPokemon: 0,
            nivelEntrenador: 0,
        );

        $this->assertSame(RolExploracion::RECOLECTOR, RolExploracion::sugeridoPara($stats));
    }

    /** El rol sugerido debe ser RASTREADOR cuando la detección domina. */
    #[Test]
    public function sugerido_es_rastreador_cuando_domina_la_deteccion(): void
    {
        $stats = new CapacidadesStats(
            hp: 30,
            atk: 30,
            def: 30,
            spAtk: 30,
            spDef: 95,
            speed: 95,
            nivelPokemon: 0,
            nivelEntrenador: 0,
        );

        $this->assertSame(RolExploracion::RASTREADOR, RolExploracion::sugeridoPara($stats));
    }

    /** El rol sugerido debe ser VANGUARDIA cuando la supervivencia domina. */
    #[Test]
    public function sugerido_es_vanguardia_cuando_domina_la_supervivencia(): void
    {
        $stats = new CapacidadesStats(
            hp: 95,
            atk: 20,
            def: 95,
            spAtk: 20,
            spDef: 20,
            speed: 20,
            nivelPokemon: 0,
            nivelEntrenador: 0,
        );

        $this->assertSame(RolExploracion::VANGUARDIA, RolExploracion::sugeridoPara($stats));
    }

    /** Desempate determinista: COMBATIENTE > RECOLECTOR > RASTREADOR > VANGUARDIA. */
    #[Test]
    public function sugerido_usa_desempate_determinista_combatiente_primero(): void
    {
        // Todas las capacidades idénticas → debe ganar COMBATIENTE (prioridad 1).
        $stats = new CapacidadesStats(
            hp: 50,
            atk: 50,
            def: 50,
            spAtk: 50,
            spDef: 50,
            speed: 50,
            nivelPokemon: 0,
            nivelEntrenador: 0,
        );

        $this->assertSame(RolExploracion::COMBATIENTE, RolExploracion::sugeridoPara($stats));
    }
}
