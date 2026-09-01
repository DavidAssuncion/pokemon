<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CalculadorMatchups;
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
        // Planta contra pool Fuego/Volador: ambos matchups negativos (defensa 1.5) →
        // matchups críticos → Fracaso asegurado aunque la capacidad sea alta.
        $resultado = CalculadorRiesgo::evaluar(
            peligro: 1,
            nivel: 1,
            capacidad: 90,
            tiposPool: [TipoPokemon::FUEGO, TipoPokemon::VOLADOR],
            miembros: $this->miembros([[TipoPokemon::PLANTA]]),
        );

        $this->assertSame('Extremo', $resultado['riesgo']);
        $this->assertNotEmpty($resultado['advertencias']);
        // Los textos de tipo ya NO van en advertencias (viven en matchups).
        foreach ($resultado['advertencias'] as $advertencia) {
            $this->assertStringNotContainsString('Pokémon de tipo', $advertencia);
        }
        $this->assertCount(2, $resultado['matchups']);
        $this->assertSame(['Planta'], $resultado['matchups'][0]['miembro_tipos']);
        $this->assertSame('Fuego', $resultado['matchups'][0]['pool_tipo']);
        $this->assertSame(1.5, $resultado['matchups'][0]['defensa']);
        $this->assertSame(CalculadorMatchups::NEGATIVO, $resultado['matchups'][0]['clasificacion']);
        $this->assertSame('Volador', $resultado['matchups'][1]['pool_tipo']);
        $this->assertSame(CalculadorMatchups::NEGATIVO, $resultado['matchups'][1]['clasificacion']);
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

    public function test_evaluar_incluye_matchups_en_el_json(): void
    {
        $resultado = CalculadorRiesgo::evaluar(
            peligro: 1,
            nivel: 1,
            capacidad: 50,
            tiposPool: [TipoPokemon::AGUA, TipoPokemon::PLANTA],
            miembros: $this->miembros([[TipoPokemon::FUEGO], [TipoPokemon::AGUA]]),
        );

        $this->assertArrayHasKey('matchups', $resultado);
        // 2 miembros × 2 tipos pool = 4 matchups total; neutrales filtrados.
        // Fuego vs Agua: negativo; Fuego vs Planta: positivo; Agua vs Agua: positivo; Agua vs Planta: negativo.
        $this->assertCount(4, $resultado['matchups']);
        // Orden: por miembro y por tipo del pool.
        $this->assertSame('Fuego', $resultado['matchups'][0]['miembro_tipos'][0]);
        $this->assertSame('Agua', $resultado['matchups'][0]['pool_tipo']);
        $this->assertSame('Fuego', $resultado['matchups'][1]['miembro_tipos'][0]);
        $this->assertSame('Planta', $resultado['matchups'][1]['pool_tipo']);
        $this->assertSame('Agua', $resultado['matchups'][2]['miembro_tipos'][0]);
        $this->assertSame('Agua', $resultado['matchups'][2]['pool_tipo']);
        $this->assertSame('Agua', $resultado['matchups'][3]['miembro_tipos'][0]);
        $this->assertSame('Planta', $resultado['matchups'][3]['pool_tipo']);
    }

    public function test_matchups_omite_los_neutrales(): void
    {
        // Veneno vs Agua → neutral (defensa 1.0, ataque 1.0) → se omite.
        $resultado = CalculadorRiesgo::evaluar(
            peligro: 1,
            nivel: 1,
            capacidad: 50,
            tiposPool: [TipoPokemon::AGUA, TipoPokemon::LUCHA],
            miembros: $this->miembros([[TipoPokemon::VENENO]]),
        );

        // Veneno vs Agua = neutral (omitido); Veneno vs Lucha = neutro (defensa 1.0, ataque 1.0) → omitido. Matchups vacío.
        // Pero Veneno vs Lucha: defensa = Lucha→Veneno = 0.5 → positivo (no neutral).
        // Lucha row: VENENO 0.5. So defensa = 0.5 → positivo.
        // Veneno vs Agua: defensa = Agua→Veneno = 1.0, ataque = Veneno→Agua = 1.0 → neutral → omitido.
        $this->assertCount(1, $resultado['matchups']);
        $this->assertSame('Veneno', $resultado['matchups'][0]['miembro_tipos'][0]);
        $this->assertSame('Lucha', $resultado['matchups'][0]['pool_tipo']);
        $this->assertSame(CalculadorMatchups::POSITIVO, $resultado['matchups'][0]['clasificacion']);
    }

    public function test_miembro_con_matchup_severo_advertencias_no_vacias_y_riesgo_extremo(): void
    {
        // Veneno vs Acero → severo (ataque 0.0). Capacidad suficiente para no ser débiles.
        $resultado = CalculadorRiesgo::evaluar(
            peligro: 1,
            nivel: 1,
            capacidad: 80,
            tiposPool: [TipoPokemon::ACERO],
            miembros: $this->miembros([[TipoPokemon::VENENO]]),
        );

        $this->assertNotEmpty($resultado['advertencias']);
        $this->assertSame('Extremo', $resultado['riesgo']);
        $this->assertCount(1, $resultado['matchups']);
        $this->assertSame(CalculadorMatchups::SEVERO, $resultado['matchups'][0]['clasificacion']);
    }

    public function test_equipo_con_solo_matchups_positivos_riesgo_por_capacidad_normal(): void
    {
        // Fuego contra Planta → positivo. Capacidad suficiente para riesgo Bajo.
        $resultado = CalculadorRiesgo::evaluar(
            peligro: 1,
            nivel: 1,
            capacidad: 80,
            tiposPool: [TipoPokemon::PLANTA],
            miembros: $this->miembros([[TipoPokemon::FUEGO]]),
        );

        $this->assertSame('Bajo', $resultado['riesgo']);
        $this->assertSame(['Equipo bien preparado para esta zona'], $resultado['advertencias']);
        $this->assertCount(1, $resultado['matchups']);
        $this->assertSame(CalculadorMatchups::POSITIVO, $resultado['matchups'][0]['clasificacion']);
    }

    public function test_matchup_negativo_con_capacidad_alta_sigue_siendo_extremo(): void
    {
        // Fuego contra Agua → negativo. Capacidad alta pero riesgo Extremo por matchup.
        $resultado = CalculadorRiesgo::evaluar(
            peligro: 1,
            nivel: 1,
            capacidad: 100,
            tiposPool: [TipoPokemon::AGUA],
            miembros: $this->miembros([[TipoPokemon::FUEGO]]),
        );

        $this->assertSame('Extremo', $resultado['riesgo']);
        $this->assertSame(['Equipo bien preparado para esta zona'], $resultado['advertencias']);
        $this->assertCount(1, $resultado['matchups']);
        $this->assertSame(CalculadorMatchups::NEGATIVO, $resultado['matchups'][0]['clasificacion']);
    }
}
