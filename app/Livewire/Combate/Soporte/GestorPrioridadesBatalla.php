<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ObjetivoMovimiento;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ServicioEjecucionBatalla;

/**
 * Declara y resuelve las prioridades de la ronda. Sin estado: recibe el
 * componente y el agregado por parámetro y usa el contexto de ejecución.
 */
final class GestorPrioridadesBatalla
{
    public function __construct(
        private readonly ResolutorAccionBatalla $resolutorAccion,
        private readonly GestorVistaBatalla $gestorVista,
    ) {
    }

    public function declararIa(AgregadoBatalla $battle): void
    {
        foreach ($battle->team2->combatientesCollection()->vivos() as $rival) {
            $mejor = null;

            foreach ($rival->pokemon()->moves()->all() as $movimiento) {
                if ($movimiento->priority <= 0 || ! $rival->movimientoDisponible($movimiento)) {
                    continue;
                }

                if ($mejor === null
                    || $movimiento->priority > $mejor->priority
                    || ($movimiento->priority === $mejor->priority && $movimiento->potencia > $mejor->potencia)) {
                    $mejor = $movimiento;
                }
            }

            if ($mejor !== null) {
                $rival->setPostura(PosturaCombate::ATACAR);
                $rival->setObjetivoCubiertoId('');
                $rival->declararPrioridad($mejor);
            }
        }
    }

    public function resolver(
        Combate $componente,
        AgregadoBatalla $battle,
        ContextoEjecucionBatalla $contexto,
    ): void {
        /** @var list<array{actor: Combatiente, movimiento: MovimientoBatalla}> $declaradas */
        $declaradas = [];
        foreach ($battle->turnManager()->combatientesVivos() as $combatiente) {
            $movimiento = $combatiente->prioridadDeclarada();
            if ($movimiento !== null) {
                $declaradas[] = ['actor' => $combatiente, 'movimiento' => $movimiento];
            }
        }

        if ($declaradas === []) {
            return;
        }

        $turnos = $battle->turnManager();
        $invertir = $battle->trickRoomActivo();
        usort($declaradas, function (array $a, array $b) use ($turnos, $invertir): int {
            $porPrioridad = $b['movimiento']->priority <=> $a['movimiento']->priority;
            if ($porPrioridad !== 0) {
                return $porPrioridad;
            }

            $va = $turnos->velocidadEfectivaDe($a['actor']);
            $vb = $turnos->velocidadEfectivaDe($b['actor']);

            return $invertir ? $va <=> $vb : $vb <=> $va;
        });

        $servicio = $contexto->servicio() ?? new ServicioEjecucionBatalla($battle->damageChain());

        foreach ($declaradas as $declarada) {
            /** @var Combatiente $actor */
            $actor = $declarada['actor'];
            /** @var MovimientoBatalla $movimiento */
            $movimiento = $declarada['movimiento'];
            $actor->limpiarPrioridad();

            if (! $battle->turnManager()->bothTeamsAlive()) {
                break;
            }

            if (! $actor->estaVivo()) {
                continue;
            }

            if (! $actor->movimientoDisponible($movimiento)) {
                $actor->romperCombo();
                $componente->log[] = "{$actor->nombre()} no puede usar {$movimiento->nombre} todavía.";
                $turnos->consumeAction($actor);

                continue;
            }

            $puedeActuar = $actor->puedeActuar();
            if (! $puedeActuar->esPermitida()) {
                $componente->log[] = "{$actor->nombre()} no puede usar {$movimiento->nombre}: {$puedeActuar->motivo()}";
                if ($puedeActuar->autoDanio() > 0) {
                    $componente->log[] = "¡{$actor->nombre()} se golpeó a sí mismo! ({$puedeActuar->autoDanio()} daño)";
                }
                $turnos->consumeAction($actor);

                continue;
            }

            $objetivo = $this->objetivoPara($battle, $actor, $movimiento);
            if ($objetivo === null) {
                $actor->romperCombo();
                $componente->log[] = "{$actor->nombre()} no encuentra objetivo para {$movimiento->nombre}.";
                $turnos->consumeAction($actor);

                continue;
            }

            $isAi = $battle->team2->findCombatantById($actor->id()) !== null;
            $resultadoAccion = $this->resolutorAccion->aplicarMovimiento(
                $battle,
                $actor,
                $objetivo,
                $movimiento,
                $servicio,
                $isAi ? 'RIVAL PRIORIDAD: ' : 'PRIORIDAD: ',
                $isAi ? 'RIVAL: ' : '',
                false,
                $componente->eventoTick,
                count($componente->eventos) + 1,
            );
            $componente->log = array_merge($componente->log, $resultadoAccion->log);
            $componente->eventos = array_merge($componente->eventos, $resultadoAccion->eventos);
        }

        $this->gestorVista->sincronizar($componente, $battle);
    }

    private function objetivoPara(
        AgregadoBatalla $battle,
        Combatiente $actor,
        MovimientoBatalla $movimiento,
    ): ?Combatiente {
        $autodirigido = $movimiento->esAutodirigido()
            || $movimiento->objetivo === ObjetivoMovimiento::ALL;

        if ($autodirigido) {
            return $actor;
        }

        return $battle->elegirObjetivoPara($actor);
    }
}
