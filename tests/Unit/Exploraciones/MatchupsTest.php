<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CalculadorMatchups;
use Src\Exploraciones\Domain\RolExploracion;
use Src\Shared\Tipos\TipoPokemon;

class MatchupsTest extends TestCase
{
    /**
     * @param  list<list<TipoPokemon>>  $tiposPorMiembro
     * @return list<array{tipos: list<TipoPokemon>, enPool: bool, rol: RolExploracion, base: int}>
     */
    private function miembros(array $tiposPorMiembro): array
    {
        $miembros = [];
        foreach ($tiposPorMiembro as $tipos) {
            $miembros[] = [
                'tipos' => $tipos,
                'enPool' => true,
                'rol' => RolExploracion::COMBATIENTE,
                'base' => 60,
            ];
        }

        return $miembros;
    }

    /**
     * Matchup del único miembro (con sus tipos) contra el único tipo del pool.
     *
     * @param  list<TipoPokemon>  $tiposMiembro
     * @return array{miembro_tipos: list<string>, pool_tipo: string, defensa: float, ataque: float, clasificacion: string}
     */
    private function unicoMatchup(array $tiposMiembro, TipoPokemon $poolTipo): array
    {
        $matchups = CalculadorMatchups::calcular($this->miembros([$tiposMiembro]), [$poolTipo]);

        $this->assertCount(1, $matchups);

        return $matchups[0];
    }

    public function test_fuego_en_pool_agua_es_negativo(): void
    {
        $m = $this->unicoMatchup([TipoPokemon::FUEGO], TipoPokemon::AGUA);

        $this->assertSame(['Fuego'], $m['miembro_tipos']);
        $this->assertSame('Agua', $m['pool_tipo']);
        $this->assertSame(1.5, $m['defensa']);
        $this->assertSame(0.75, $m['ataque']);
        $this->assertSame(CalculadorMatchups::NEGATIVO, $m['clasificacion']);
    }

    public function test_agua_en_pool_agua_es_positivo(): void
    {
        $m = $this->unicoMatchup([TipoPokemon::AGUA], TipoPokemon::AGUA);

        $this->assertSame(0.75, $m['defensa']);
        $this->assertSame(0.75, $m['ataque']);
        $this->assertSame(CalculadorMatchups::POSITIVO, $m['clasificacion']);
    }

    public function test_veneno_en_pool_agua_es_neutral_oculto(): void
    {
        $this->assertSame(
            CalculadorMatchups::NEUTRAL,
            CalculadorMatchups::clasificar(1.0, 1.0),
        );
        // Neutral → se omite del resultado.
        $this->assertSame([], CalculadorMatchups::calcular(
            $this->miembros([[TipoPokemon::VENENO]]),
            [TipoPokemon::AGUA],
        ));
    }

    public function test_psiquico_en_pool_agua_es_neutral_oculto(): void
    {
        $this->assertSame(
            CalculadorMatchups::NEUTRAL,
            CalculadorMatchups::clasificar(1.0, 1.0),
        );
        $this->assertSame([], CalculadorMatchups::calcular(
            $this->miembros([[TipoPokemon::PSIQUICO]]),
            [TipoPokemon::AGUA],
        ));
    }

    public function test_veneno_en_pool_acero_es_severo_por_inmunidad_ofensiva(): void
    {
        $m = $this->unicoMatchup([TipoPokemon::VENENO], TipoPokemon::ACERO);

        $this->assertSame(1.0, $m['defensa']);
        $this->assertSame(0.25, $m['ataque']);
        $this->assertSame(CalculadorMatchups::SEVERO, $m['clasificacion']);
    }

    public function test_fuego_en_pool_hielo_es_positivo(): void
    {
        $m = $this->unicoMatchup([TipoPokemon::FUEGO], TipoPokemon::HIELO);

        $this->assertSame(0.75, $m['defensa']);
        $this->assertSame(1.5, $m['ataque']);
        $this->assertSame(CalculadorMatchups::POSITIVO, $m['clasificacion']);
    }

    public function test_fuego_en_pool_acero_es_positivo(): void
    {
        $m = $this->unicoMatchup([TipoPokemon::FUEGO], TipoPokemon::ACERO);

        $this->assertSame(0.75, $m['defensa']);
        $this->assertSame(1.5, $m['ataque']);
        $this->assertSame(CalculadorMatchups::POSITIVO, $m['clasificacion']);
    }

    public function test_acero_en_pool_planta_es_positivo_por_resistencia_defensiva(): void
    {
        // El caso de referencia "Planta en pool Acero → positivo (defensa 0.5)" es una
        // transposición: Acero→Planta = 1.0 (no 0.5) en el TypeChart. El valor 0.5
        // corresponde a Planta→Acero, es decir, miembro Acero contra pool Planta.
        $m = $this->unicoMatchup([TipoPokemon::ACERO], TipoPokemon::PLANTA);

        $this->assertSame(0.75, $m['defensa']);
        $this->assertSame(1.0, $m['ataque']);
        $this->assertSame(CalculadorMatchups::POSITIVO, $m['clasificacion']);
    }

    public function test_planta_en_pool_acero_es_negativo_por_desempate_ofensivo(): void
    {
        // Combinación literal del brief: miembro Planta contra pool Acero. Según el
        // TypeChart real (Acero→Planta = 1.0, Planta→Acero = 0.5) es NEGATIVO, no positivo.
        $m = $this->unicoMatchup([TipoPokemon::PLANTA], TipoPokemon::ACERO);

        $this->assertSame(1.0, $m['defensa']);
        $this->assertSame(0.75, $m['ataque']);
        $this->assertSame(CalculadorMatchups::NEGATIVO, $m['clasificacion']);
    }

    public function test_psiquico_en_pool_siniestro_es_severo_por_inmunidad_ofensiva(): void
    {
        $m = $this->unicoMatchup([TipoPokemon::PSIQUICO], TipoPokemon::SINIESTRO);

        $this->assertSame(1.5, $m['defensa']);
        $this->assertSame(0.25, $m['ataque']);
        $this->assertSame(CalculadorMatchups::SEVERO, $m['clasificacion']);
    }

    public function test_fuego_en_pool_siniestro_es_neutral_oculto(): void
    {
        $this->assertSame(
            CalculadorMatchups::NEUTRAL,
            CalculadorMatchups::clasificar(1.0, 1.0),
        );
        $this->assertSame([], CalculadorMatchups::calcular(
            $this->miembros([[TipoPokemon::FUEGO]]),
            [TipoPokemon::SINIESTRO],
        ));
    }

    public function test_doble_tipo_fuego_volador_en_pool_agua_defensa_multiplicativa_y_ataque_max(): void
    {
        $m = $this->unicoMatchup([TipoPokemon::FUEGO, TipoPokemon::VOLADOR], TipoPokemon::AGUA);

        $this->assertSame(['Fuego', 'Volador'], $m['miembro_tipos']);
        $this->assertSame(1.5 * 1.0, $m['defensa']);
        $this->assertSame(max(0.75, 1.0), $m['ataque']);
        $this->assertSame(CalculadorMatchups::NEGATIVO, $m['clasificacion']);
    }

    public function test_doble_tipo_fuego_volador_en_pool_bicho_defensa_multiplicativa_y_ataque_max(): void
    {
        // Nota: el caso de referencia del brief dice "0.75×0.75=0.5625" pero el TypeChart
        // real da Bicho→Volador = 0.75 y Bicho→Fuego = 0.75. La defensa real es 0.75×0.75 = 0.5625.
        // La clasificación (positivo) es correcta porque defensa < 1.0.
        $m = $this->unicoMatchup([TipoPokemon::FUEGO, TipoPokemon::VOLADOR], TipoPokemon::BICHO);

        $this->assertSame(0.75 * 0.75, $m['defensa']);
        $this->assertSame(max(1.5, 1.5), $m['ataque']);
        $this->assertSame(CalculadorMatchups::POSITIVO, $m['clasificacion']);
    }

    public function test_inmunidad_defensiva_es_positivo_y_nunca_severo(): void
    {
        // Eléctrico→Tierra = 0.25 (inmunidad defensiva del miembro) → ventaja, positivo.
        $m = $this->unicoMatchup([TipoPokemon::TIERRA], TipoPokemon::ELECTRICO);

        $this->assertSame(0.25, $m['defensa']);
        $this->assertSame(1.5, $m['ataque']);
        $this->assertSame(CalculadorMatchups::POSITIVO, $m['clasificacion']);
    }

    public function test_severo_prevalece_aunque_haya_inmunidad_defensiva(): void
    {
        // Normal contra Fantasma: defensa 0.25 (no le pegan) PERO ataque 0.25 (no daña) →
        // severo por prioridad estricta del ataque.
        $m = $this->unicoMatchup([TipoPokemon::NORMAL], TipoPokemon::FANTASMA);

        $this->assertSame(0.25, $m['defensa']);
        $this->assertSame(0.25, $m['ataque']);
        $this->assertSame(CalculadorMatchups::SEVERO, $m['clasificacion']);
    }

    public function test_desempate_ofensivo_defensa_neutral_con_ataque_mayor_es_positivo(): void
    {
        $m = $this->unicoMatchup([TipoPokemon::LUCHA], TipoPokemon::NORMAL);

        $this->assertSame(1.0, $m['defensa']);
        $this->assertSame(1.5, $m['ataque']);
        $this->assertSame(CalculadorMatchups::POSITIVO, $m['clasificacion']);
    }

    public function test_calcular_genera_un_matchup_por_miembro_y_por_tipo_del_pool_en_orden(): void
    {
        $matchups = CalculadorMatchups::calcular(
            $this->miembros([[TipoPokemon::FUEGO], [TipoPokemon::PLANTA]]),
            [TipoPokemon::AGUA, TipoPokemon::BICHO],
        );

        $this->assertSame('Fuego', $matchups[0]['miembro_tipos'][0]);
        $this->assertSame('Agua', $matchups[0]['pool_tipo']);
        $this->assertSame('Bicho', $matchups[1]['pool_tipo']);
        $this->assertSame('Planta', $matchups[2]['miembro_tipos'][0]);
        $this->assertSame('Agua', $matchups[2]['pool_tipo']);
        $this->assertSame('Bicho', $matchups[3]['pool_tipo']);
    }

    public function test_hay_matchup_critico_detecta_negativo_y_severo(): void
    {
        $matchups = CalculadorMatchups::calcular(
            $this->miembros([[TipoPokemon::FUEGO], [TipoPokemon::AGUA], [TipoPokemon::VENENO]]),
            [TipoPokemon::AGUA, TipoPokemon::ACERO],
        );

        $this->assertTrue(CalculadorMatchups::hayMatchupCritico($matchups));
    }

    public function test_hay_matchup_critico_es_falso_sin_negativos_ni_severos(): void
    {
        $matchups = CalculadorMatchups::calcular(
            $this->miembros([[TipoPokemon::FUEGO], [TipoPokemon::ACERO]]),
            [TipoPokemon::PLANTA],
        );

        $this->assertFalse(CalculadorMatchups::hayMatchupCritico($matchups));
    }
}
