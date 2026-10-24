<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Rototiller: sube Ataque y At. Especial de los Pokémon de tipo Planta.
 */
class EjecutorRototiller extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::ROTOTILLER;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $cambioAtk = CambioStat::desdeEtapas(StatClave::ATAQUE, 1);
        $cambioSpa = CambioStat::desdeEtapas(StatClave::ATAQUE_ESPECIAL, 1);

        foreach ([$battle->team1, $battle->team2] as $equipo) {
            foreach ($equipo->combatientesCollection()->vivos() as $combatiente) {
                if (! $combatiente->tiposDefensivos()->contiene(TipoPokemon::PLANTA)) {
                    continue;
                }

                $combatiente->setMultiplicadores($cambioAtk->aplicadoTemporalEn($combatiente->multiplicadores()));
                $combatiente->setMultiplicadores($cambioSpa->aplicadoTemporalEn($combatiente->multiplicadores()));
            }
        }

        $battle->agregarLog('¡El terreno se remueve y los Pokémon de tipo Planta se fortalecen!');
    }
}
