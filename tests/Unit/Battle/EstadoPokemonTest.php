<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Estados alterados: daño por ronda (BURN) y bloqueo de acción (SLEEP/PARALYSIS).
 */
class EstadoPokemonTest extends TestCase
{
    use ConstruyeCombatientes;

    public function test_burn_causa_dano_por_ronda(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 160, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::FUEGO],
            id: 'c1',
            nombre: 'Quemado',
        );
        $combatiente->setEstado(EstadoPokemon::BURN);

        $maxHp = $combatiente->pokemon()->battleStats()->hp;
        $daño = $combatiente->aplicarDañoStatus();

        // Daño = max(1, maxHp * 6.25%)
        $this->assertSame(max(1.0, $maxHp * 0.0625), $daño);
        $this->assertSame($maxHp - $daño, $combatiente->hpActual());
    }

    public function test_sleep_impide_actuar(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::PSIQUICO],
            id: 'c1',
            nombre: 'Dormido',
        );
        $combatiente->setEstado(EstadoPokemon::SLEEP);
        $combatiente->setTurnosEstado(2);

        $result = $combatiente->puedeActuar();

        $this->assertFalse($result['canAct']);
        $this->assertSame('está dormido', $result['reason']);
    }

    public function test_paralysis_puede_impedir_actuar(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::ELECTRICO],
            id: 'c1',
            nombre: 'Paralizado',
        );
        $combatiente->setEstado(EstadoPokemon::PARALYSIS);

        mt_srand(5); // seed=5 → mt_rand(1,100)=12 ≤ 25 → bloquea
        $result = $combatiente->puedeActuar();

        $this->assertFalse($result['canAct']);
        $this->assertSame('está paralizado', $result['reason']);
    }

    public function test_sin_estado_puede_actuar(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'c1',
            nombre: 'Sano',
        );

        $result = $combatiente->puedeActuar();

        $this->assertTrue($result['canAct']);
    }

    public function test_poison_causa_dano_por_ronda_12_5(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 160, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'c1',
            nombre: 'Envenenado',
        );
        $combatiente->setEstado(EstadoPokemon::POISON);

        $maxHp = $combatiente->pokemon()->battleStats()->hp;
        $daño = $combatiente->aplicarDañoStatus();

        $this->assertSame(max(1.0, $maxHp * 0.125), $daño);
        $this->assertSame($maxHp - $daño, $combatiente->hpActual());
    }

    public function test_bad_poison_dano_creciente_por_contador(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 160, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'c1',
            nombre: 'Gravemente Envenenado',
        );
        $combatiente->setEstado(EstadoPokemon::BAD_POISON);
        $combatiente->setContadorVenenoGrave(1);

        $maxHp = $combatiente->pokemon()->battleStats()->hp;

        // Primera ronda: contador 1 → maxHp * 1/16
        $primerDaño = $combatiente->aplicarDañoStatus();
        $this->assertSame(max(1.0, $maxHp * 1 / 16), $primerDaño);

        // Segunda ronda: contador ya incrementado → maxHp * 2/16
        $segundoDaño = $combatiente->aplicarDañoStatus();
        $this->assertSame(max(1.0, $maxHp * 2 / 16), $segundoDaño);
        $this->assertSame(3, $combatiente->contadorVenenoGrave());
    }

    public function test_sin_estado_no_causa_dano(): void
    {
        $combatiente = $this->combatiente(
            stats: ['hp' => 160, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::NORMAL],
            id: 'c1',
            nombre: 'Sano',
        );

        $maxHp = $combatiente->hpActual();
        $daño = $combatiente->aplicarDañoStatus();

        $this->assertSame(0.0, $daño);
        $this->assertSame($maxHp, $combatiente->hpActual());
    }
}
