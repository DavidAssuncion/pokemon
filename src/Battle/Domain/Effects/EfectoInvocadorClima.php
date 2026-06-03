<?php

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\AgregadoBatalla;

/**
 * Activa un clima al iniciar la batalla.
 * Se parametriza con el tipo de clima a establecer.
 *
 * Ej: 'sequia' → $battle->weather = 'sequia'
 */
class EfectoInvocadorClima implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly string $clima,
    ) {}

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $battle->weather = $this->clima;
        $nombres = [
            'sequia' => '¡Sequía! El sol abrasa el campo de batalla',
            'diluvio' => '¡Diluvio! La lluvia torrencial inunda todo',
            'niebla' => '¡Niebla! Una bruma mística envuelve el campo',
            'granizo' => '¡Granizo! El hielo cae del cielo',
            'tormenta_arena' => '¡Tormenta de arena! La arena azota el campo',
            'turbulencias' => '¡Turbulencias! Vientos huracanados sacuden el campo',
        ];
        $battle->log[] = $nombres[$this->clima] ?? "Clima: {$this->clima}";
    }
}
