<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate\Soporte\DTO\VistaBatalla;
use App\Livewire\Presenters\DebugCombatientePresenter;
use Src\Battle\App\ObtenerOpcionesObjeto;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoTrampa;
use Src\Battle\Domain\EquipoBatalla;

/**
 * Proyecta el agregado al estado público del componente sin consumir dos
 * veces el log: `syncViewData()` sigue siendo el único punto que lo fusiona.
 */
final class SincronizadorVistaBatalla
{
    public function __construct(
        private ObtenerOpcionesObjeto $opcionesObjeto,
    ) {
    }

    public function proyectar(
        AgregadoBatalla $battle,
        ?Combatiente $actor,
        ?Combatiente $actorParaObjetos,
        int $usuarioId,
    ): VistaBatalla {
        $weather = $battle->weather()->value;
        $logNuevo = $battle->log()->entries()->toList();
        $battle->limpiarLog();

        $debug = (bool) config('app.debug');

        $team1 = array_map(
            fn (Combatiente $c) => $c->aArrayVista(0),
            $battle->team1->combatants()->all(),
        );

        $team2 = array_map(
            function (Combatiente $c) use ($debug): array {
                $vista = $c->aArrayVista(1);

                if ($debug) {
                    $vista['debug'] = DebugCombatientePresenter::para($c);
                }

                return $vista;
            },
            $battle->team2->combatants()->all(),
        );

        $objetosCombate = $this->objetosCombate($battle, $actorParaObjetos, $usuarioId);

        if ($actor !== null) {
            $canHitRetaguardia = ! $actor->estaEnVanguardia() || ! $battle->team2->tieneVanguardiaViva();

            foreach ($team2 as &$p) {
                $p['canTarget'] = $p['posicion'] !== 'retaguardia' || $canHitRetaguardia;
            }
            unset($p);
        }

        return new VistaBatalla(
            weather: $weather,
            logNuevo: $logNuevo,
            team1: $team1,
            team2: $team2,
            objetosCombate: $objetosCombate,
            trampas1: $this->trampas($battle->team1),
            trampas2: $this->trampas($battle->team2),
        );
    }

    /**
     * @return list<array{clave: string, label: string, acumulaciones: int}>
     */
    private function trampas(EquipoBatalla $equipo): array
    {
        $trampas = [];

        foreach ($equipo->efectos()->all() as $efecto) {
            if (! $efecto instanceof EfectoEquipoTrampa) {
                continue;
            }

            $trampas[] = [
                'clave' => $efecto->obtenerClave(),
                'label' => $efecto->label(),
                'acumulaciones' => $efecto->acumulaciones,
            ];
        }

        return $trampas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function objetosCombate(
        AgregadoBatalla $battle,
        ?Combatiente $actor,
        int $usuarioId,
    ): array {
        if ($usuarioId <= 0) {
            return [];
        }

        if ($actor === null || ! $actor->estaVivo()) {
            return [];
        }

        return $this->opcionesObjeto
            ->handle($usuarioId, $battle, $actor)
            ->toArray();
    }
}
