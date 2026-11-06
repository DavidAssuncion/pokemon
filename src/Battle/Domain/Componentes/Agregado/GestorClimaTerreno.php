<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Agregado;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\Enums\TipoTerreno;
use Src\Battle\Domain\GestorTurnos;

/**
 * Clima, terreno y Trick Room del agregado. Sin estado: opera sobre los
 * colaboradores recibidos en cada llamada.
 */
final class GestorClimaTerreno
{
    public function aplicarClima(GestorTurnos $turnos, TipoClima $clima): void
    {
        foreach ($turnos->allCombatants() as $combatiente) {
            $combatiente->setClima($clima);
        }
    }

    public function normalizarTurnosTerreno(TipoTerreno $terreno, int $turnos): int
    {
        return $terreno === TipoTerreno::NONE ? 0 : max(1, $turnos);
    }

    public function fijarTrickRoom(GestorTurnos $gestor, int $turnos): int
    {
        $normalizados = max(0, $turnos);
        $gestor->setInvertirVelocidad($normalizados > 0);

        return $normalizados;
    }

    public function avanzarTurnos(AgregadoBatalla $batalla): void
    {
        if ($batalla->trickRoomTurnos() > 0) {
            $batalla->setTrickRoom($batalla->trickRoomTurnos() - 1);
        }

        if ($batalla->turnosTerreno() > 0) {
            $restantes = $batalla->turnosTerreno() - 1;
            $batalla->setTerreno(
                $restantes === 0 ? TipoTerreno::NONE : $batalla->terreno(),
                $restantes,
            );
        }
    }
}
