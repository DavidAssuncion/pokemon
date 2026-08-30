<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\Effects\EfectoRestos;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Restos: cura 1/16 del HP máximo al final de cada ronda (sin superar el máximo).
 */
class EfectoRestosTest extends TestCase
{
    use ConstruyeCombatientes;

    public function test_restos_cura_1_16_cada_ronda(): void
    {
        $portador = $this->combatiente(
            stats: ['hp' => 160, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'p1',
            nombre: 'Portador',
            item: 'leftovers',
        );
        $portador->effects()->add(new EfectoRestos('leftovers'));

        $enemigo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'e1',
            nombre: 'Enemigo',
        );

        $battle = $this->batallaMinima($portador, $enemigo);

        $maxHp = $portador->pokemon()->battleStats()->hp;

        // Dañar al portador antes del fin de ronda
        $portador->setHpActual($maxHp * 0.5);

        $portador->effects()->triggerRoundEnd($portador, $battle);

        // Cura = max(1, maxHp * 1/16), sin superar el máximo
        $this->assertSame($maxHp * 0.5 + max(1, $maxHp / 16), $portador->hpActual());
    }

    public function test_restos_no_supera_hp_maximo(): void
    {
        $portador = $this->combatiente(
            stats: ['hp' => 160, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'p1',
            nombre: 'Portador',
            item: 'leftovers',
        );
        $portador->effects()->add(new EfectoRestos('leftovers'));

        $enemigo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'e1',
            nombre: 'Enemigo',
        );

        $battle = $this->batallaMinima($portador, $enemigo);

        $maxHp = $portador->pokemon()->battleStats()->hp;

        // Portador casi lleno: la cura queda clampada al máximo
        $portador->setHpActual($maxHp - 1);

        $portador->effects()->triggerRoundEnd($portador, $battle);

        $this->assertSame($maxHp, $portador->hpActual());
    }

    public function test_restos_no_curan_si_portador_muerto(): void
    {
        $portador = $this->combatiente(
            stats: ['hp' => 160, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::VENENO],
            id: 'p1',
            nombre: 'Portador',
            item: 'leftovers',
        );
        $portador->effects()->add(new EfectoRestos('leftovers'));
        $portador->setHpActual(0);

        $enemigo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'e1',
            nombre: 'Enemigo',
        );

        $battle = $this->batallaMinima($portador, $enemigo);

        $portador->effects()->triggerRoundEnd($portador, $battle);

        $this->assertSame(0.0, $portador->hpActual());
        $this->assertEmpty($battle->log());
    }
}
