<?php

declare(strict_types=1);

namespace Tests\Unit\Battle;

use PHPUnit\Framework\TestCase;
use Src\Battle\Domain\Effects\EfectoInvocadorClima;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Efecto invocador de clima: establece el clima al disparar onBattleStart.
 */
class EfectoInvocadorClimaTest extends TestCase
{
    use ConstruyeCombatientes;

    public function test_sequia_establece_clima_en_battle_start(): void
    {
        $portador = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 100, 'def' => 100],
            tipos: [TipoPokemon::FUEGO],
            id: 'p1',
            nombre: 'Invocador',
        );
        $portador->effects()->add(new EfectoInvocadorClima('sequia_summoner', TipoClima::SEQUIA));

        $enemigo = $this->combatiente(
            stats: ['hp' => 100, 'atk' => 80, 'def' => 100],
            tipos: [TipoPokemon::AGUA],
            id: 'e1',
            nombre: 'Enemigo',
        );

        $battle = $this->batallaMinima($portador, $enemigo);
        $this->assertSame(TipoClima::NONE, $battle->weather());

        $battle->triggerBattleStartEffects();

        $this->assertSame(TipoClima::SEQUIA, $battle->weather());
    }
}
