<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Shared\Tipos\TipoPokemon;

/**
 * ManejadorClima: aplica ±25% según tipo de movimiento y clima.
 */
class ManejadorClimaTest extends TestCase
{
    use ConstruyeCombatientes;

    private CadenaDanio $chain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chain = new CadenaDanio();
    }

    public function test_sequia_fuego_125(): void
    {
        $daño = $this->calcular(TipoPokemon::FUEGO, TipoClima::SEQUIA);

        $this->assertSame(30.0, $daño); // 24 * 1.25
    }

    public function test_sequia_agua_075(): void
    {
        $daño = $this->calcular(TipoPokemon::AGUA, TipoClima::SEQUIA);

        $this->assertSame(18.0, $daño); // 24 * 0.75
    }

    public function test_sin_clima_1(): void
    {
        $daño = $this->calcular(TipoPokemon::FUEGO, TipoClima::NONE);

        $this->assertSame(24.0, $daño);
    }

    public function test_diluvio_agua_125(): void
    {
        $daño = $this->calcular(TipoPokemon::AGUA, TipoClima::DILUVIO);

        $this->assertSame(30.0, $daño); // 24 * 1.25
    }

    public function test_diluvio_fuego_075(): void
    {
        $daño = $this->calcular(TipoPokemon::FUEGO, TipoClima::DILUVIO);

        $this->assertSame(18.0, $daño); // 24 * 0.75
    }

    public function test_niebla_siniestro_125(): void
    {
        // Defensor AGUA: efectividad 1.0 contra SINIESTRO
        $daño = $this->calcular(TipoPokemon::SINIESTRO, TipoClima::NIEBLA, tiposDefensor: [TipoPokemon::AGUA]);

        $this->assertSame(30.0, $daño); // 24 * 1.25
    }

    public function test_niebla_fantasma_125(): void
    {
        // Defensor AGUA: efectividad 1.0 contra FANTASMA (NORMAL sería 0.0 por inmunidad)
        $daño = $this->calcular(TipoPokemon::FANTASMA, TipoClima::NIEBLA, tiposDefensor: [TipoPokemon::AGUA]);

        $this->assertSame(30.0, $daño); // 24 * 1.25
    }

    public function test_niebla_psiquico_125(): void
    {
        // Defensor AGUA: efectividad 1.0 contra PSIQUICO
        $daño = $this->calcular(TipoPokemon::PSIQUICO, TipoClima::NIEBLA, tiposDefensor: [TipoPokemon::AGUA]);

        $this->assertSame(30.0, $daño); // 24 * 1.25
    }

    public function test_turbulencias_dragon_125(): void
    {
        $daño = $this->calcular(TipoPokemon::DRAGON, TipoClima::TURBULENCIAS);

        $this->assertSame(30.0, $daño); // 24 * 1.25
    }

    public function test_turbulencias_volador_125(): void
    {
        $daño = $this->calcular(TipoPokemon::VOLADOR, TipoClima::TURBULENCIAS);

        $this->assertSame(30.0, $daño); // 24 * 1.25
    }

    public function test_granizo_hielo_especial_080(): void
    {
        $daño = $this->calcular(
            TipoPokemon::HIELO,
            TipoClima::GRANIZO,
            categoria: CategoriaMovimiento::ESPECIAL,
        );

        $this->assertSame(19.0, $daño); // floor(24 * 0.80) = 19
    }

    public function test_tormenta_arena_fisico_roca_080(): void
    {
        $daño = $this->calcular(
            TipoPokemon::DRAGON,
            TipoClima::TORMENTA_ARENA,
            categoria: CategoriaMovimiento::FISICO,
            tiposDefensor: [TipoPokemon::ROCA],
        );

        $this->assertSame(19.0, $daño); // floor(24 * 0.80) = 19
    }

    public function test_granizo_fisico_hielo_no_reduce(): void
    {
        $daño = $this->calcular(
            TipoPokemon::HIELO,
            TipoClima::GRANIZO,
            categoria: CategoriaMovimiento::FISICO,
        );

        $this->assertSame(24.0, $daño); // solo reduce movimientos ESPECIALES de HIELO
    }

    private function calcular(
        TipoPokemon $tipoMovimiento,
        TipoClima $weather,
        CategoriaMovimiento $categoria = CategoriaMovimiento::FISICO,
        array $tiposDefensor = [TipoPokemon::NORMAL],
    ): float {
        // Atacante tipo VENENO → sin STAB; defensor NORMAL por defecto → efectividad 1.0
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            moves: [new MovimientoBatalla('Golpe', 50, $tipoMovimiento, $categoria)],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: $tiposDefensor,
            id: 'd1',
            nombre: 'Defensor',
        );

        $accion = new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: new MovimientoBatalla('Golpe', 50, $tipoMovimiento, $categoria),
            fromPosition: Posicion::VANGUARDIA,
            defenderTeamHasVanguard: false,
            weather: $weather,
        );

        mt_srand(1); // sin crítico

        return $this->chain->calculate($accion);
    }
}
