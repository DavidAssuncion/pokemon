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

    private function calcular(TipoPokemon $tipoMovimiento, TipoClima $weather): float
    {
        // Atacante tipo VENENO → sin STAB con FUEGO/AGUA; defensor NORMAL → efectividad 1.0
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            moves: [new MovimientoBatalla('Golpe', 50, $tipoMovimiento, CategoriaMovimiento::FISICO)],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'd1',
            nombre: 'Defensor',
        );

        $accion = new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: new MovimientoBatalla('Golpe', 50, $tipoMovimiento, CategoriaMovimiento::FISICO),
            fromPosition: Posicion::VANGUARDIA,
            defenderTeamHasVanguard: false,
            weather: $weather,
        );

        mt_srand(1); // sin crítico

        return $this->chain->calculate($accion);
    }
}
