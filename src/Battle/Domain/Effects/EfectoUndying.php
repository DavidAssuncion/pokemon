<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDanioRecibido;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * Undying: una vez por batalla, si el portador recibiría daño letal de un
 * ataque, se queda a 1 HP en lugar de debilitarse.
 *
 * La reacción se ejecuta antes del contraataque, así que un portador con
 * Counter y Undying puede sobrevivir y devolver el golpe. El flag `usado`
 * se conserva en la sesión junto con el resto de efectos.
 */
class EfectoUndying implements InterfazEfecto, ReaccionaAlDanioRecibido
{
    use ComportamientosPorDefecto;

    private bool $usado = false;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function alRecibirDanio(
        Combatiente $portador,
        AccionBatalla $accion,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        if ($this->usado || $portador->estaVivo()) {
            return;
        }

        $portador->setHpActual(1.0);
        $this->usado = true;

        $battle->agregarLog("¡{$portador->nombre()} resiste con 1 HP gracias a Undying!");
    }
}
