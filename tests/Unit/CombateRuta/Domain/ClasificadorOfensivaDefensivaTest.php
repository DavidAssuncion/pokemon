<?php

declare(strict_types=1);

namespace Tests\Unit\CombateRuta\Domain;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\Posicion;
use Src\Pokemon\Domain\Stats\DatosStats;
use Src\Shared\Domain\ClasificadorOfensivaDefensiva;

class ClasificadorOfensivaDefensivaTest extends TestCase
{
    private ClasificadorOfensivaDefensiva $clasificador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clasificador = new ClasificadorOfensivaDefensiva();
    }

    private function stats(int $hp, int $atk, int $def, int $spAtk, int $spDef, int $speed): DatosStats
    {
        return new DatosStats(hp: $hp, atk: $atk, def: $def, spAtk: $spAtk, spDef: $spDef, speed: $speed);
    }

    #[Test]
    public function es_ofensivo_cuando_ataque_mas_velocidad_supera_defensa_mas_sdef_mas_hp(): void
    {
        // ofensiva = 120 + 110 = 230; defensiva = 80 + 70 + 90 = 240 → defensivo
        $this->assertFalse(
            $this->clasificador->esOfensivo(
                $this->stats(hp: 90, atk: 120, def: 80, spAtk: 40, spDef: 70, speed: 110)
            )
        );

        // ofensiva = 150 + 130 = 280; defensiva = 80 + 70 + 90 = 240 → ofensivo
        $this->assertTrue(
            $this->clasificador->esOfensivo(
                $this->stats(hp: 90, atk: 150, def: 80, spAtk: 40, spDef: 70, speed: 130)
            )
        );
    }

    #[Test]
    public function empate_va_a_vanguardia_defensiva(): void
    {
        // ofensiva = 100 + 100 = 200; defensiva = 80 + 70 + 50 = 200 → empate → defensivo
        $this->assertFalse(
            $this->clasificador->esOfensivo(
                $this->stats(hp: 50, atk: 100, def: 80, spAtk: 40, spDef: 70, speed: 100)
            )
        );
    }

    #[Test]
    public function generar_formacion_determinista_y_mixta(): void
    {
        $stats = [
            $this->stats(hp: 50, atk: 150, def: 60, spAtk: 30, spDef: 50, speed: 140),  // ofensiva=290 > defensiva=160 → RET
            $this->stats(hp: 80, atk: 60, def: 120, spAtk: 30, spDef: 110, speed: 40),   // defensiva=310 > ofensiva=100 → VAN
            $this->stats(hp: 100, atk: 130, def: 70, spAtk: 40, spDef: 60, speed: 120),  // ofensiva=250 > defensiva=230 → RET
            $this->stats(hp: 90, atk: 50, def: 100, spAtk: 30, spDef: 90, speed: 30),    // defensiva=280 > ofensiva=80 → VAN
            $this->stats(hp: 60, atk: 110, def: 80, spAtk: 40, spDef: 70, speed: 100),   // ofensiva=210 > defensiva=210 → empate → VAN
        ];

        $formacion = $this->clasificador->generarFormacion($stats);

        $this->assertCount(5, $formacion);
        $this->assertSame(Posicion::RETAGUARDIA, $formacion[0]);
        $this->assertSame(Posicion::VANGUARDIA, $formacion[1]);
        $this->assertSame(Posicion::RETAGUARDIA, $formacion[2]);
        $this->assertSame(Posicion::VANGUARDIA, $formacion[3]);
        $this->assertSame(Posicion::VANGUARDIA, $formacion[4]);
    }

    #[Test]
    public function edge_case_todos_en_mismo_bando_mueve_al_mayor_stat_opuesto(): void
    {
        // Todos ofensivos: ofensiva > defensiva para todos
        $stats = [
            $this->stats(hp: 40, atk: 150, def: 50, spAtk: 30, spDef: 40, speed: 140),  // of=290, def=130 → RET
            $this->stats(hp: 50, atk: 140, def: 60, spAtk: 30, spDef: 50, speed: 130),  // of=270, def=160 → RET
            $this->stats(hp: 45, atk: 130, def: 55, spAtk: 25, spDef: 45, speed: 120),  // of=250, def=145 → RET
            $this->stats(hp: 55, atk: 120, def: 65, spAtk: 35, spDef: 55, speed: 110),  // of=230, def=175 → RET
            $this->stats(hp: 60, atk: 100, def: 70, spAtk: 30, spDef: 60, speed: 100),  // of=200, def=190 → RET
        ];

        $formacion = $this->clasificador->generarFormacion($stats);

        $this->assertCount(5, $formacion);
        // 4 RET + 1 VAN → el de mayor defensiva (hp=60, def=70, spDef=60 = 190) → VANGUARDIA
        $this->assertSame(Posicion::VANGUARDIA, $formacion[4]); // idx 4 tiene mayor defensiva (190)
        $this->assertSame(Posicion::RETAGUARDIA, $formacion[0]);
        $this->assertSame(Posicion::RETAGUARDIA, $formacion[1]);
        $this->assertSame(Posicion::RETAGUARDIA, $formacion[2]);
        $this->assertSame(Posicion::RETAGUARDIA, $formacion[3]);
    }

    #[Test]
    public function edge_case_todos_defensivos_mueve_al_mayor_ofensiva(): void
    {
        // Todos defensivos: defensiva >= ofensiva para todos
        $stats = [
            $this->stats(hp: 100, atk: 50, def: 120, spAtk: 30, spDef: 110, speed: 40),  // def=330, of=120 → VAN
            $this->stats(hp: 90, atk: 60, def: 110, spAtk: 35, spDef: 100, speed: 50),   // def=300, of=145 → VAN
            $this->stats(hp: 80, atk: 70, def: 100, spAtk: 40, spDef: 90, speed: 60),    // def=270, of=170 → VAN
            $this->stats(hp: 70, atk: 80, def: 90, spAtk: 45, spDef: 80, speed: 70),     // def=240, of=195 → VAN
            $this->stats(hp: 120, atk: 40, def: 130, spAtk: 25, spDef: 120, speed: 35),  // def=370, of=100 → VAN
        ];

        $formacion = $this->clasificador->generarFormacion($stats);

        $this->assertCount(5, $formacion);
        // 4 VAN + 1 RET → el de mayor ofensiva (atk=80, speed=70 = 150) → RETAGUARDIA
        $this->assertSame(Posicion::RETAGUARDIA, $formacion[3]); // idx 3 tiene mayor ofensiva (150)
        $this->assertSame(Posicion::VANGUARDIA, $formacion[0]);
        $this->assertSame(Posicion::VANGUARDIA, $formacion[1]);
        $this->assertSame(Posicion::VANGUARDIA, $formacion[2]);
        $this->assertSame(Posicion::VANGUARDIA, $formacion[4]);
    }

    #[Test]
    public function edge_case_todos_ret_empate_defensiva_elige_el_primer_maximo(): void
    {
        // Todos RET; idx3 e idx4 empatan en defensiva (170) → el empate lo
        // gana el PRIMERO (idx3), no el último (idx4).
        $stats = [
            $this->stats(hp: 40, atk: 150, def: 50, spAtk: 30, spDef: 40, speed: 140),  // of=290, def=130 → RET
            $this->stats(hp: 50, atk: 140, def: 60, spAtk: 30, spDef: 50, speed: 130),  // of=270, def=160 → RET
            $this->stats(hp: 45, atk: 130, def: 55, spAtk: 25, spDef: 45, speed: 120),  // of=250, def=145 → RET
            $this->stats(hp: 50, atk: 120, def: 60, spAtk: 35, spDef: 60, speed: 110),  // of=230, def=170 → RET
            $this->stats(hp: 60, atk: 100, def: 50, spAtk: 30, spDef: 60, speed: 100),  // of=200, def=170 → RET (empate)
        ];

        $formacion = $this->clasificador->generarFormacion($stats);

        $this->assertSame(Posicion::VANGUARDIA, $formacion[3]);
        $this->assertSame(Posicion::RETAGUARDIA, $formacion[4]);
    }

    #[Test]
    public function edge_case_todos_ret_mayor_defensiva_por_un_punto_gana(): void
    {
        // Todos RET; idx4 supera a idx3 por UN punto (171 vs 170): la lógica
        // debe elegir idx4 aunque la diferencia sea mínima.
        $stats = [
            $this->stats(hp: 40, atk: 150, def: 50, spAtk: 30, spDef: 40, speed: 140),  // of=290, def=130 → RET
            $this->stats(hp: 50, atk: 140, def: 60, spAtk: 30, spDef: 50, speed: 130),  // of=270, def=160 → RET
            $this->stats(hp: 45, atk: 130, def: 55, spAtk: 25, spDef: 45, speed: 120),  // of=250, def=145 → RET
            $this->stats(hp: 50, atk: 120, def: 60, spAtk: 35, spDef: 60, speed: 110),  // of=230, def=170 → RET
            $this->stats(hp: 51, atk: 100, def: 60, spAtk: 30, spDef: 60, speed: 100),  // of=200, def=171 → RET
        ];

        $formacion = $this->clasificador->generarFormacion($stats);

        $this->assertSame(Posicion::VANGUARDIA, $formacion[4]);
        $this->assertSame(Posicion::RETAGUARDIA, $formacion[3]);
    }

    #[Test]
    public function generar_formacion_es_determinista(): void
    {
        $stats = [
            $this->stats(hp: 50, atk: 100, def: 80, spAtk: 30, spDef: 70, speed: 100),
            $this->stats(hp: 80, atk: 60, def: 120, spAtk: 30, spDef: 110, speed: 40),
            $this->stats(hp: 100, atk: 110, def: 90, spAtk: 40, spDef: 80, speed: 100),
            $this->stats(hp: 90, atk: 50, def: 100, spAtk: 30, spDef: 90, speed: 30),
            $this->stats(hp: 60, atk: 90, def: 70, spAtk: 40, spDef: 60, speed: 80),
        ];

        $primera = $this->clasificador->generarFormacion($stats);
        $segunda = $this->clasificador->generarFormacion($stats);

        $this->assertSame($primera, $segunda);
    }
}
