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
 * ManejadorPosicion: ataque a retaguardia con vanguardia enemiga viva → ×0.5.
 */
class ManejadorPosicionTest extends TestCase
{
    use ConstruyeCombatientes;

    private CadenaDanio $chain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chain = new CadenaDanio();
    }

    public function test_retaguardia_con_vanguardia_enemiga_viva_50_por_ciento(): void
    {
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            moves: [new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Retaguardia',
            posicion: Posicion::RETAGUARDIA,
        );

        $accion = new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
            fromPosition: Posicion::VANGUARDIA,
            defenderTeamHasVanguard: true,
            weather: TipoClima::NONE,
        );

        mt_srand(1); // sin crítico
        $daño = $this->chain->calculate($accion);

        $this->assertSame(12.0, $daño); // 24 * 0.5
    }

    public function test_vanguardia_sin_penalizacion(): void
    {
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            moves: [new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Vanguardia',
        );

        $accion = new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
            fromPosition: Posicion::VANGUARDIA,
            defenderTeamHasVanguard: true,
            weather: TipoClima::NONE,
        );

        mt_srand(1); // sin crítico
        $daño = $this->chain->calculate($accion);

        $this->assertSame(24.0, $daño);
    }
}
