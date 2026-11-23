<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate;
use Illuminate\Contracts\Auth\Guard;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * Vuelca la proyección del agregado sobre las propiedades públicas del
 * componente. No posee estado: el sincronizador y el resolutor son sus
 * únicas dependencias.
 */
final class GestorVistaBatalla
{
    public function __construct(
        private readonly SincronizadorVistaBatalla $sincronizador,
        private readonly ResolutorActorBatalla $resolutorActor,
        private readonly Guard $guard,
    ) {
    }

    public function sincronizar(
        Combate $componente,
        AgregadoBatalla $battle,
        ?Combatiente $actor = null,
    ): void {
        $actorParaVista = $actor;
        if ($actorParaVista !== null && $battle->team1->findCombatant($actorParaVista) === null) {
            $actorParaVista = null;
        }

        $vista = $this->sincronizador->proyectar(
            $battle,
            $actorParaVista,
            $actor ?? $this->resolutorActor->porRefId($componente->actingRefId, $battle),
            (int) $this->guard->id(),
        );

        $componente->weather = $vista->weather;
        $componente->log = array_merge($componente->log, $vista->logNuevo);
        $componente->team1 = $vista->team1;
        $componente->team2 = $vista->team2;
        $componente->objetosCombate = $vista->objetosCombate;
        $componente->trampas1 = $vista->trampas1;
        $componente->trampas2 = $vista->trampas2;
    }
}
