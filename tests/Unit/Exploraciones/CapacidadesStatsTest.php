<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CapacidadesStats;
use Src\Exploraciones\Domain\RangoCapacidad;

class CapacidadesStatsTest extends TestCase
{
    /**
     * Construye stats donde una sola capacidad toma el valor exacto pedido:
     * movilidad = speed + niveles, con niveles 0 → valor exacto.
     */
    private function conMovilidad(int $velocidad): CapacidadesStats
    {
        return new CapacidadesStats(
            hp: 0,
            atk: 0,
            def: 0,
            spAtk: 0,
            spDef: 0,
            speed: $velocidad,
            nivelPokemon: 0,
            nivelEntrenador: 0,
        );
    }

    /**
     * Construye stats con deteccion = valor exacto (speed = spDef = valor).
     */
    private function conDeteccion(int $valor): CapacidadesStats
    {
        return new CapacidadesStats(
            hp: 0,
            atk: 0,
            def: 0,
            spAtk: 0,
            spDef: $valor,
            speed: $valor,
            nivelPokemon: 0,
            nivelEntrenador: 0,
        );
    }

    public function test_rango_de_asigna_rango_segun_umbrales_multiplicativos(): void
    {
        $casos = [
            [34, RangoCapacidad::NOVATO],
            [35, RangoCapacidad::COMPETENTE],
            [70, RangoCapacidad::EXPERTO],
            [123, RangoCapacidad::MAESTRO],
        ];

        foreach ($casos as [$valor, $esperado]) {
            $stats = $this->conMovilidad($valor);
            $this->assertSame($esperado, $stats->rangoDe('movilidad', 35), "Valor {$valor} con dificultad 35");
        }
    }

    public function test_rango_de_usa_la_dificultad_del_habitat_como_referencia(): void
    {
        $stats = $this->conMovilidad(40);

        $this->assertSame(RangoCapacidad::COMPETENTE, $stats->rangoDe('movilidad', 35));
        $this->assertSame(RangoCapacidad::MAESTRO, $stats->rangoDe('movilidad', 10));
        $this->assertSame(RangoCapacidad::NOVATO, $stats->rangoDe('movilidad', 50));
    }

    public function test_rango_deteccion_envuelve_rango_de(): void
    {
        $stats = $this->conDeteccion(75);

        $this->assertSame(RangoCapacidad::EXPERTO, $stats->rangoDeteccion(35));
    }

    public function test_bonus_caramelos_recoleccion_equivale_al_rango(): void
    {
        // recoleccion = 0.25*(spDef+speed+hp+def) + niveles → valor = X si los 4 son X
        $stats = new CapacidadesStats(
            hp: 70,
            atk: 0,
            def: 70,
            spAtk: 0,
            spDef: 70,
            speed: 70,
            nivelPokemon: 0,
            nivelEntrenador: 0,
        );

        $this->assertSame(2, $stats->bonusCaramelosRecoleccion(35));
        $this->assertSame(RangoCapacidad::EXPERTO, $stats->rangoDe('recoleccion', 35));
    }

    public function test_bonus_eventos_exploracion_equivale_al_rango(): void
    {
        // exploracion con los 4 stats a 75 → ≈0.998*75 = 74.85 → EXPERTO (70 ≤ v < 122.5)
        $stats = new CapacidadesStats(
            hp: 75,
            atk: 0,
            def: 75,
            spAtk: 0,
            spDef: 75,
            speed: 75,
            nivelPokemon: 0,
            nivelEntrenador: 0,
        );

        $this->assertSame(2, $stats->bonusEventosExploracion(35));
    }

    public function test_multiplicador_recuperacion_por_rango_de_supervivencia(): void
    {
        // supervivencia = 0.33*(hp+def+spDef) + niveles
        $casos = [
            [30, 1.00],   // 29.7 → NOVATO
            [40, 1.25],   // 39.6 → COMPETENTE
            [75, 1.50],   // 74.25 → EXPERTO
            [140, 1.75],  // 138.6 → MAESTRO
        ];

        foreach ($casos as [$stat, $esperado]) {
            $stats = new CapacidadesStats(
                hp: $stat,
                atk: 0,
                def: $stat,
                spAtk: 0,
                spDef: $stat,
                speed: 0,
                nivelPokemon: 0,
                nivelEntrenador: 0,
            );
            $this->assertSame($esperado, $stats->multiplicadorRecuperacion(35), "Stat {$stat}");
        }
    }

    public function test_reduccion_intervalo_movilidad_por_rango(): void
    {
        $casos = [
            [30, 0.0],
            [40, 0.10],
            [75, 0.25],
            [130, 0.40],
        ];

        foreach ($casos as [$valor, $esperado]) {
            $stats = $this->conMovilidad($valor);
            $this->assertSame($esperado, $stats->reduccionIntervaloMovilidad(35), "Valor {$valor}");
        }
    }

    public function test_bonus_dano_combate_por_rango(): void
    {
        $casos = [
            [30, 1.0],
            [40, 1.05],
            [75, 1.10],
            [130, 1.15],
        ];

        foreach ($casos as [$stat, $esperado]) {
            $stats = new CapacidadesStats(
                hp: 0,
                atk: $stat,
                def: $stat,
                spAtk: $stat,
                spDef: $stat,
                speed: 0,
                nivelPokemon: 0,
                nivelEntrenador: 0,
            );
            $this->assertSame($esperado, $stats->bonusDanoCombate(35), "Stat {$stat}");
        }
    }

    public function test_deteccion_auto_evasion_solo_con_maestro(): void
    {
        $this->assertTrue($this->conDeteccion(130)->deteccionAutoEvasion(35));
        $this->assertFalse($this->conDeteccion(75)->deteccionAutoEvasion(35));
        $this->assertFalse($this->conDeteccion(40)->deteccionAutoEvasion(35));
    }

    public function test_permitir_emboscadas_requiere_competente(): void
    {
        $this->assertTrue($this->conDeteccion(40)->permitirEmboscadas(35));
        $this->assertTrue($this->conDeteccion(130)->permitirEmboscadas(35));
        $this->assertFalse($this->conDeteccion(30)->permitirEmboscadas(35));
    }

    public function test_permitir_excepcionales_requiere_experto(): void
    {
        $this->assertTrue($this->conDeteccion(75)->permitirExcepcionales(35));
        $this->assertFalse($this->conDeteccion(40)->permitirExcepcionales(35));
    }

    public function test_rango_de_rechaza_capacidad_desconocida(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->conMovilidad(50)->rangoDe('volar', 35);
    }
}
