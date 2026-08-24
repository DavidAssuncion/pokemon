<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Presentation\DTOResultadoDanio;

/**
 * Servicio compartido que ejecuta el cálculo y aplicación de un movimiento.
 * Unifica la lógica duplicada entre Combate (Livewire) y AgregadoBatalla::ejecutarAccion (auto).
 */
class ServicioEjecucionBatalla
{
    public function __construct(
        private readonly CadenaDanio $damageChain,
    ) {
    }

    /**
     * Calcula y aplica el daño de un movimiento.
     * No maneja logging, animaciones ni efectos (eso es responsabilidad del caller).
     */
    public function calcularYAplicarDano(AccionBatalla $accion): DTOResultadoDanio
    {
        $directPct = $accion->attacker->obtenerPorcentajeDanioDirecto();

        $daño = $this->damageChain->calculate($accion);
        $accion->defender->recibirDaño($daño, $accion->move->esEspecial(), $directPct);

        return new DTOResultadoDanio(dano: $daño, directPct: $directPct);
    }

    /**
     * Aplica el estado secundario del movimiento al objetivo.
     */
    public function aplicarEstado(Combatiente $objetivo, MovimientoBatalla $movimiento): void
    {
        if (! $movimiento->tieneStatus() || ! $objetivo->estaVivo()) {
            return;
        }

        $objetivo->setEstado($movimiento->statusEffect);
        $objetivo->setTurnosEstado(match ($movimiento->statusEffect) {
            EstadoPokemon::SLEEP => mt_rand(2, 4),
            EstadoPokemon::CONFUSION => mt_rand(2, 4),
            default => 0,
        });
    }

    /**
     * Aplica cambios de estadísticas del movimiento.
     */
    public function aplicarStatChanges(Combatiente $actor, Combatiente $objetivo, MovimientoBatalla $movimiento): void
    {
        foreach ($movimiento->selfStatChanges as $cambio) {
            $actor->aplicarCambioEtapa($cambio['stat'], $cambio['stages']);
        }
        foreach ($movimiento->targetStatChanges as $cambio) {
            $objetivo->aplicarCambioEtapa($cambio['stat'], $cambio['stages']);
        }
    }

    /**
     * Genera el mensaje de log descriptivo del movimiento ejecutado.
     */
    public function generarLogMovimiento(
        Combatiente $actor,
        Combatiente $objetivo,
        MovimientoBatalla $movimiento,
        float $daño,
        float $directPct,
        bool $defenderTeamHasVanguard,
    ): string {
        $dmgPart = $daño > 0 ? " → {$daño} de daño a {$objetivo->nombre()}" : '';
        $logMsg = "{$actor->nombre()} usa {$movimiento->nombre}{$dmgPart}";

        if ($daño > 0 && $objetivo->estaEnRetaguardia() && $defenderTeamHasVanguard) {
            $logMsg .= ' (-50% retaguardia)';
        }

        if ($daño > 0 && $directPct > 0) {
            $logMsg .= ' ['.($directPct * 100).'% directo]';
        }

        return $logMsg;
    }
}
