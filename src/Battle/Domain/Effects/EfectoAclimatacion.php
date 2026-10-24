<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\TipoClima;

/**
 * Aclimatación: mantiene el clima neutralizado mientras el portador esté vivo.
 */
class EfectoAclimatacion implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $this->neutralizar($battle);
    }

    public function onRoundStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $this->neutralizar($battle);
    }

    private function neutralizar(AgregadoBatalla $battle): void
    {
        if ($battle->weather() !== TipoClima::NONE) {
            $battle->setWeather(TipoClima::NONE);
            $battle->agregarLog('¡Aclimatación neutraliza el clima!');
        }
    }
}
