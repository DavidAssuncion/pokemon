<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Effects\EfectoOrbeVida;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Orbe Vida: bonus de daño ×1.3 en la cadena + recoil 10% HP al infligir daño.
 */
class EfectoOrbeVidaTest extends TestCase
{
    use ConstruyeCombatientes;

    private CadenaDanio $chain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chain = new CadenaDanio();
    }

    public function test_orbe_vida_multiplica_dano_por_1_3(): void
    {
        $atacante = $this->combatiente(
            stats: ['atk' => 100, 'def' => 100],
            moves: [new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO)],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
            item: 'life_orb',
        );
        $defensor = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Defensor',
        );

        $accion = new AccionBatalla(
            attacker: $atacante,
            defender: $defensor,
            move: new MovimientoBatalla('Golpe', 50, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO),
            fromPosition: Posicion::VANGUARDIA,
            defenderTeamHasVanguard: false,
            weather: TipoClima::NONE,
        );

        mt_srand(1); // sin crítico
        $daño = $this->chain->calculate($accion);

        $this->assertSame(31.0, $daño); // 24 * 1.3 = 31.2 → floor 31
    }

    public function test_orbe_vida_recoil_10_por_ciento(): void
    {
        $atacante = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'a1',
            nombre: 'Atacante',
            item: 'life_orb',
        );
        $atacante->effects()->add(new EfectoOrbeVida('life_orb'));

        $defensor = $this->combatiente(
            stats: ['hp' => 200, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'd1',
            nombre: 'Defensor',
        );

        $battle = $this->batallaMinima($atacante, $defensor);

        $maxHp = $atacante->pokemon()->battleStats()->hp;
        $hpInicial = $atacante->hpActual();
        $atacante->dispararDanioInfligido($defensor, 50.0, $battle);

        // Recoil = 10% del HP máximo
        $this->assertSame($hpInicial - max(1, $maxHp * 0.10), $atacante->hpActual());
        $this->assertNotEmpty($battle->log());
        $this->assertStringContainsString('Orbe Vida', implode(' ', $battle->log()));
    }
}
