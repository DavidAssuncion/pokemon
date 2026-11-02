<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Observer;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * Puerto de observación de la ejecución de una batalla.
 *
 * Se inyecta por parámetro en `AgregadoBatalla::ejecutarBatalla()` y nunca se
 * guarda como propiedad del agregado: no forma parte del payload de sesión ni
 * de su versión.
 */
interface ObservadorEjecucionBatalla
{
    /**
     * Se invoca después de resolver el turno de un actor, tras consumir su
     * acción y procesar los debilitamientos pendientes.
     */
    public function alFinalizarTurno(int $ronda, Combatiente $actor, AgregadoBatalla $batalla): void;

    /**
     * Se invoca al cerrar la ronda, después de los efectos de fin de ronda.
     */
    public function alFinalizarRonda(int $ronda, AgregadoBatalla $batalla): void;
}
