<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDanioRecibido;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * Refuerzos: la primera vez que el portador recibe daño invoca un clon
 * temporal y sube Ataque/Ataque Especial/Velocidad. El clon se crea con
 * `prepararComoInvocacion`, que limpia habilidades, así que no hereda Refuerzos.
 */
class EfectoRefuerzos implements InterfazEfecto, ReaccionaAlDanioRecibido
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
        if ($this->usado || ! $portador->estaVivo() || ! $resultado->huboDano()) {
            return;
        }

        $this->usado = true;

        $battle->invocarCombatiente(
            invocador: $portador,
            turnos: 3,
            fraccionHp: 0.35,
            maxInvocaciones: 1,
            nombre: 'Clon de Refuerzos',
        );

        $portador->setMultiplicadores(
            $portador->multiplicadores()
                ->aplicarTemporal(StatClave::ATAQUE, 1.30)
                ->aplicarTemporal(StatClave::ATAQUE_ESPECIAL, 1.30)
                ->aplicarTemporal(StatClave::VELOCIDAD, 1.30),
        );

        $battle->agregarLog("¡{$portador->nombre()} activa Refuerzos y crea un clon!");
    }
}
