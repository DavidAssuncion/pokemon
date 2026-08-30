<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CalculadorCapacidadEquipo;
use Src\Exploraciones\Domain\RolExploracion;
use Src\Shared\Tipos\TipoPokemon;

class CalculadorCapacidadEquipoTest extends TestCase
{
    public function test_base_de_stats_normaliza_el_promedio_a_0_100(): void
    {
        // D6: base = stats base de especie (pokemon_stats.base_stat), 0–100.
        $this->assertSame(50, CalculadorCapacidadEquipo::baseDeStats([50, 50, 50, 50, 50, 50]));
        $this->assertSame(67, CalculadorCapacidadEquipo::baseDeStats([60, 70, 70]));
        $this->assertSame(100, CalculadorCapacidadEquipo::baseDeStats([150, 150, 150, 150, 150, 150]));
        $this->assertSame(0, CalculadorCapacidadEquipo::baseDeStats([]));
    }

    public function test_afinidad_suma_bonus_si_especie_en_pool(): void
    {
        // Sin tipos, la afinidad base es +10 si la especie está en el pool del nivel.
        $this->assertSame(10, CalculadorCapacidadEquipo::afinidadDeMiembro([], [], true));
        $this->assertSame(0, CalculadorCapacidadEquipo::afinidadDeMiembro([], [], false));
    }

    public function test_afinidad_premia_tipo_super_eficaz_contra_el_pool(): void
    {
        // Fuego (10) contra Planta (12): super-eficaz → +2 por par.
        $afinidad = CalculadorCapacidadEquipo::afinidadDeMiembro(
            [TipoPokemon::FUEGO],
            [TipoPokemon::PLANTA],
            true,
        );
        $this->assertSame(12, $afinidad);
    }

    public function test_afinidad_castiga_tipo_resistido_o_inmune(): void
    {
        // Planta contra Fuego: resistido → −1; Eléctrico contra Tierra: inmune → −2.
        $this->assertSame(9, CalculadorCapacidadEquipo::afinidadDeMiembro([TipoPokemon::PLANTA], [TipoPokemon::FUEGO], true));
        $this->assertSame(8, CalculadorCapacidadEquipo::afinidadDeMiembro([TipoPokemon::ELECTRICO], [TipoPokemon::TIERRA], true));
    }

    public function test_afinidad_respeta_los_topes_menos_20_mas_30(): void
    {
        // Miembro de tipo Hada contra muchos Lucha/Volador → +30 tope.
        $pool = array_fill(0, 12, TipoPokemon::LUCHA);
        $this->assertSame(30, CalculadorCapacidadEquipo::afinidadDeMiembro([TipoPokemon::HADA], $pool, true));

        // Miembro de tipo Planta contra 12 Fuego → −12; sin especie en pool → −12 (tope −20 no alcanzado).
        $poolFuego = array_fill(0, 12, TipoPokemon::FUEGO);
        $this->assertSame(-12, CalculadorCapacidadEquipo::afinidadDeMiembro([TipoPokemon::PLANTA], $poolFuego, false));
    }

    public function test_capacidad_miembro_suma_base_afinidad_rol_y_sinergia(): void
    {
        $this->assertSame(77, CalculadorCapacidadEquipo::capacidadMiembro(50, 10, 15, 2));
        $this->assertSame(0, CalculadorCapacidadEquipo::capacidadMiembro(-10, -20, 0, 0));
    }

    public function test_capacidad_equipo_es_el_promedio_de_miembros(): void
    {
        $this->assertSame(70, CalculadorCapacidadEquipo::capacidadEquipo([70, 70, 70]));
        $this->assertSame(77, CalculadorCapacidadEquipo::capacidadEquipo([73, 81]));
        $this->assertSame(0, CalculadorCapacidadEquipo::capacidadEquipo([]));
    }

    public function test_rol_combatiente_aporta_mas_capacidad_que_el_resto(): void
    {
        $this->assertSame(15, RolExploracion::COMBATIENTE->bonusCapacidad());
        $this->assertSame(5, RolExploracion::VANGUARDIA->bonusCapacidad());
        $this->assertSame(5, RolExploracion::RASTREADOR->bonusCapacidad());
        $this->assertSame(0, RolExploracion::RECOLECTOR->bonusCapacidad());
    }
}
