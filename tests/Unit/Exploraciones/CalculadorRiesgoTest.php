<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CalculadorRiesgo;
use Src\Exploraciones\Domain\RolExploracion;
use Src\Shared\Tipos\TipoPokemon;

class CalculadorRiesgoTest extends TestCase
{
    /**
     * @return list<array{tipos: list<TipoPokemon>, enPool: bool, rol: RolExploracion, base: int}>
     */
    private function miembros(array $tiposPorMiembro): array
    {
        $miembros = [];
        foreach ($tiposPorMiembro as $index => $tipos) {
            $miembros[] = [
                'tipos' => $tipos,
                'enPool' => true,
                'rol' => RolExploracion::COMBATIENTE,
                'base' => 60,
            ];
        }

        return $miembros;
    }

    public function test_equipo_bien_preparado_riesgo_bajo(): void
    {
        // Fuego contra pool Planta/Bicho: super-eficaz; capacidad 77 >= dificultad ×1.5.
        $resultado = CalculadorRiesgo::evaluar(
            peligro: 1,
            nivel: 1,
            capacidad: 77,
            tiposPool: [TipoPokemon::PLANTA, TipoPokemon::BICHO],
            miembros: $this->miembros([[TipoPokemon::FUEGO], [TipoPokemon::FUEGO]]),
        );

        $this->assertSame(1, $resultado['peligro_estrellas']);
        $this->assertSame(['Equipo bien preparado para esta zona'], $resultado['advertencias']);
        $this->assertSame('Bajo', $resultado['riesgo']);
    }

    public function test_peligro_estrellas_se_clampa_a_1_5(): void
    {
        $resultado = CalculadorRiesgo::evaluar(9, 1, 100, [TipoPokemon::PLANTA], $this->miembros([[TipoPokemon::FUEGO]]));
        $this->assertSame(5, $resultado['peligro_estrellas']);
    }

    public function test_tipo_sin_ventaja_genera_fracaso_asegurado(): void
    {
        // Planta contra pool Fuego/Volador: resistido/inefectivo → advertencia y riesgo extremo.
        $resultado = CalculadorRiesgo::evaluar(
            peligro: 1,
            nivel: 1,
            capacidad: 90,
            tiposPool: [TipoPokemon::FUEGO, TipoPokemon::VOLADOR],
            miembros: $this->miembros([[TipoPokemon::PLANTA]]),
        );

        $this->assertNotEmpty($resultado['advertencias']);
        $this->assertStringContainsString('Pokémon de tipo', $resultado['advertencias'][0]);
        $this->assertSame('Extremo', $resultado['riesgo']);
    }

    public function test_pokemon_debiles_para_el_nivel_es_fracaso_absoluto(): void
    {
        // capacidad 10 < dificultad (peligro 5 + nivel 3) × 5 = 40 → débiles.
        $resultado = CalculadorRiesgo::evaluar(
            peligro: 5,
            nivel: 3,
            capacidad: 10,
            tiposPool: [TipoPokemon::PLANTA],
            miembros: $this->miembros([[TipoPokemon::FUEGO]]),
        );

        $this->assertContains('Pokémon débiles para el nivel 3', $resultado['advertencias']);
        $this->assertSame('Extremo', $resultado['riesgo']);
    }

    public function test_roles_se_incluyen_en_el_preview(): void
    {
        $resultado = CalculadorRiesgo::evaluar(1, 1, 77, [TipoPokemon::PLANTA], [
            ['tipos' => [TipoPokemon::FUEGO], 'enPool' => true, 'rol' => RolExploracion::VANGUARDIA, 'base' => 60],
            ['tipos' => [TipoPokemon::FUEGO], 'enPool' => true, 'rol' => RolExploracion::RASTREADOR, 'base' => 60],
        ]);

        $this->assertSame(['VANGUARDIA', 'RASTREADOR'], $resultado['roles']);
    }

    public function test_recompensa_esperada_es_cualitativa(): void
    {
        $bajo = CalculadorRiesgo::evaluar(1, 1, 100, [TipoPokemon::PLANTA], $this->miembros([[TipoPokemon::FUEGO]]));
        $extremo = CalculadorRiesgo::evaluar(1, 1, 5, [TipoPokemon::FUEGO], $this->miembros([[TipoPokemon::PLANTA]]));

        $this->assertSame('alta', $bajo['recompensa_esperada']);
        $this->assertSame('mínima', $extremo['recompensa_esperada']);
    }
}
