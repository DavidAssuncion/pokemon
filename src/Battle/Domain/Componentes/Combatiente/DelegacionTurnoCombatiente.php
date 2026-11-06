<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\MovimientoBatalla;

trait DelegacionTurnoCombatiente
{
    public function vecesActuadoEstaRonda(): int
    {
        return $this->turno->vecesActuado();
    }

    public function tieneAccionPendienteEstaRonda(): bool
    {
        return $this->turno->tieneAccionPendiente();
    }

    public function accionesExtra(): int
    {
        return $this->turno->accionesExtra();
    }

    public function setAccionesExtra(int $accionesExtra): void
    {
        $this->turno->setAccionesExtra($accionesExtra);
    }

    public function otorgarAccionExtra(): void
    {
        $this->turno->otorgarAccionExtra();
    }

    public function reiniciarAccionExtra(): void
    {
        $this->turno->reiniciarAccionExtra();
    }

    public function retrasarTurno(): bool
    {
        return $this->estaVivo() && $this->turno->retrasar();
    }

    public function turnoRetrasado(): bool
    {
        return $this->turno->turnoRetrasado();
    }

    public function retrasoUsado(): bool
    {
        return $this->turno->retrasoUsado();
    }

    public function reiniciarRetraso(): void
    {
        $this->turno->reiniciarRetraso();
    }

    public function limpiarRetraso(): void
    {
        $this->turno->limpiarRetraso();
    }

    public function setRondaActual(int $ronda): void
    {
        $this->turno->setRondaActual($ronda);
    }

    public function aplicarCosteVelocidad(float $fraccion): void
    {
        $this->turno->aplicarCosteVelocidad($fraccion);
    }

    public function postura(): PosturaCombate
    {
        return $this->turno->postura();
    }

    public function setPostura(PosturaCombate $postura): void
    {
        $this->turno->setPostura($postura);

        if ($postura !== PosturaCombate::ATACAR) {
            $this->turno->limpiarPrioridad();
            $this->romperCombo();
        }
    }

    public function declararPrioridad(MovimientoBatalla $movimiento): void
    {
        $this->turno->declararPrioridad($movimiento);
    }

    public function prioridadDeclarada(): ?MovimientoBatalla
    {
        return $this->turno->prioridad();
    }

    public function limpiarPrioridad(): void
    {
        $this->turno->limpiarPrioridad();
    }

    public function tienePrioridadDeclarada(): bool
    {
        return $this->turno->tienePrioridad();
    }

    public function objetivoCubiertoId(): string
    {
        return $this->turno->objetivoCubiertoId();
    }

    public function setObjetivoCubiertoId(string $objetivoCubiertoId): void
    {
        $this->turno->setObjetivoCubiertoId($objetivoCubiertoId);
    }
}
