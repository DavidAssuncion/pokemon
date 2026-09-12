<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\MultiplicadoresStats;

/**
 * MultiplicadoresStats (sucesor de EtapasStats): clamp de factores −6..+6
 * (0.25–4.0) y tabla exacta de conversión etapa → multiplicador.
 */
class EtapasStatsTest extends TestCase
{
    public function test_aplicar_cambio_clampea_a_superior(): void
    {
        $multiplicadores = MultiplicadoresStats::desdeEtapas(['attack' => 5])
            ->aplicarFactor(StatClave::ATAQUE, MultiplicadoresStats::factorDesdeStages(5));

        $this->assertSame(4.0, $multiplicadores->obtenerMultiplicador(StatClave::ATAQUE));
    }

    public function test_aplicar_cambio_clampea_a_inferior(): void
    {
        $multiplicadores = MultiplicadoresStats::desdeEtapas(['attack' => -5])
            ->aplicarFactor(StatClave::ATAQUE, MultiplicadoresStats::factorDesdeStages(-5));

        $this->assertSame(0.25, $multiplicadores->obtenerMultiplicador(StatClave::ATAQUE));
    }

    public function test_multiplicador_positivo(): void
    {
        $multiplicadores = MultiplicadoresStats::desdeEtapas(['attack' => 2]);

        $this->assertSame(2.0, $multiplicadores->obtenerMultiplicador(StatClave::ATAQUE));
    }

    public function test_multiplicador_negativo(): void
    {
        $multiplicadores = MultiplicadoresStats::desdeEtapas(['attack' => -2]);

        $this->assertSame(0.5, $multiplicadores->obtenerMultiplicador(StatClave::ATAQUE));
    }

    public function test_aplicar_cambio_es_inmutable(): void
    {
        $original = MultiplicadoresStats::desdeEtapas(['attack' => 1]);
        $nueva = $original->aplicarFactor(StatClave::ATAQUE, MultiplicadoresStats::factorDesdeStages(1));

        $this->assertSame(1.5, $original->obtenerMultiplicador(StatClave::ATAQUE));
        // aplicarFactor multiplica: 1.5 × 1.5 = 2.25 (el original no se altera)
        $this->assertSame(2.25, $nueva->obtenerMultiplicador(StatClave::ATAQUE));
    }

    public function test_desde_etapas_clampea_etapa_mayor_que_6(): void
    {
        // factorDesdeStages(7) = 4.5 → clampeado a 4.0 (equivalente a etapa 6)
        $multiplicadores = MultiplicadoresStats::desdeEtapas(['attack' => 7]);

        $this->assertSame(4.0, $multiplicadores->obtenerMultiplicador(StatClave::ATAQUE));
    }

    public function test_desde_etapas_clampea_etapa_menor_que_menos_6(): void
    {
        // factorDesdeStages(-7) = 0.2222 → clampeado a 0.25 (equivalente a etapa -6)
        $multiplicadores = MultiplicadoresStats::desdeEtapas(['attack' => -7]);

        $this->assertSame(0.25, $multiplicadores->obtenerMultiplicador(StatClave::ATAQUE));
    }

    public function test_es_neutro_solo_cuando_todos_los_factores_son_1(): void
    {
        $multiplicadores = MultiplicadoresStats::desdeEtapas([
            'attack' => 2,
            'defense' => 0,
            'spAtk' => -1,
            'speed' => 0,
        ]);

        $this->assertFalse($multiplicadores->esNeutro());

        // Etapas 0 → factor 1.0 → neutro
        $this->assertTrue(MultiplicadoresStats::desdeEtapas(['defense' => 0, 'speed' => 0])->esNeutro());
    }

    public function test_multiplicador_extremo_superior_6_es_4(): void
    {
        $multiplicadores = MultiplicadoresStats::desdeEtapas(['attack' => 6]);

        $this->assertSame(4.0, $multiplicadores->obtenerMultiplicador(StatClave::ATAQUE));
    }

    public function test_multiplicador_extremo_inferior_menos_6_es_0_25(): void
    {
        $multiplicadores = MultiplicadoresStats::desdeEtapas(['attack' => -6]);

        $this->assertSame(0.25, $multiplicadores->obtenerMultiplicador(StatClave::ATAQUE));
    }
}
