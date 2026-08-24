<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\TipoClima;

/**
 * Activa un clima al iniciar la batalla.
 * Se parametriza con el tipo de clima a establecer.
 */
class EfectoInvocadorClima implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly TipoClima $clima,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $battle->setWeather($this->clima);
        $nombres = [
            TipoClima::SEQUIA->value => '¡Sequía! El sol abrasa el campo de batalla',
            TipoClima::DILUVIO->value => '¡Diluvio! La lluvia torrencial inunda todo',
            TipoClima::NIEBLA->value => '¡Niebla! Una bruma mística envuelve el campo',
            TipoClima::GRANIZO->value => '¡Granizo! El hielo cae del cielo',
            TipoClima::TORMENTA_ARENA->value => '¡Tormenta de arena! La arena azota el campo',
            TipoClima::TURBULENCIAS->value => '¡Turbulencias! Vientos huracanados sacuden el campo',
        ];
        $battle->agregarLog($nombres[$this->clima->value] ?? "Clima: {$this->clima->value}");
    }
}
