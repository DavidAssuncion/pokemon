<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use Src\Battle\Domain\ServicioEjecucionBatalla;

/**
 * Estado efímero de una ejecución de batalla: el servicio de ejecución
 * reutilizable y el actor forzado por `swapTurno()`. Vive una sola request.
 */
final class ContextoEjecucionBatalla
{
    private ?ServicioEjecucionBatalla $servicio = null;

    private ?string $forzarSiguienteActorId = null;

    public function servicio(): ?ServicioEjecucionBatalla
    {
        return $this->servicio;
    }

    public function fijarServicio(?ServicioEjecucionBatalla $servicio): void
    {
        $this->servicio = $servicio;
    }

    public function forzarSiguienteActor(): ?string
    {
        return $this->forzarSiguienteActorId;
    }

    public function fijarForzarSiguienteActor(?string $forzarSiguienteActorId): void
    {
        $this->forzarSiguienteActorId = $forzarSiguienteActorId;
    }
}
