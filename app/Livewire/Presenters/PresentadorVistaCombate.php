<?php

declare(strict_types=1);

namespace App\Livewire\Presenters;

use App\Livewire\Combate;
use Src\Battle\Domain\Catalog\CatalogoEfectosAliado;

/**
 * Compone el contrato de vista de `livewire.combate` a partir del estado
 * público del componente. Sin estado: solo transforma datos de vista.
 */
final class PresentadorVistaCombate
{
    /**
     * @return array<string, mixed>
     */
    public function datos(Combate $componente): array
    {
        $actorAdmiteAliado = self::actorAdmiteAliado($componente->currentMoves);
        $actorAutoobjetivoSeleccionable = self::actorAdmiteAutoobjetivo($componente->currentMoves);

        $vista = BatallaVistaPresenter::toView(
            team1: $componente->team1,
            team2: $componente->team2,
            turnQueue: $componente->turnQueue,
            phase: $componente->phase,
            actingRefId: $componente->actingRefId,
            selectedTargetRefId: $componente->selectedTargetRefId,
            animAttackerId: $componente->animAttackerId,
            animDefenderId: $componente->animDefenderId,
            animAttackerNombre: $componente->animAttackerNombre,
            animDefenderNombre: $componente->animDefenderNombre,
            animMoveNombre: $componente->animMoveNombre,
            weather: $componente->weather,
            log: $componente->log,
            eventos: $componente->eventos,
            rewards: $componente->rewards,
            objetivosItem: $componente->objetivosItem,
            actorAdmiteAliado: $actorAdmiteAliado,
            actorAutoobjetivoSeleccionable: $actorAutoobjetivoSeleccionable,
            trampas1: $componente->trampas1,
            trampas2: $componente->trampas2,
        );

        $vista['movesResumen'] = MovesPreviewPresenter::resumen($componente->currentMoves);

        return $vista;
    }

    /**
     * Indica si el actor tiene al menos un movimiento del catálogo aliado.
     *
     * @param  array<int, array<string, mixed>>  $currentMoves
     */
    private static function actorAdmiteAliado(array $currentMoves): bool
    {
        foreach ($currentMoves as $move) {
            if (CatalogoEfectosAliado::admite((string) ($move['slug'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indica si el actor tiene al menos un movimiento autoobjetivable del catálogo.
     *
     * @param  array<int, array<string, mixed>>  $currentMoves
     */
    private static function actorAdmiteAutoobjetivo(array $currentMoves): bool
    {
        foreach ($currentMoves as $move) {
            if (CatalogoEfectosAliado::permiteAutoobjetivo((string) ($move['slug'] ?? ''))) {
                return true;
            }
        }

        return false;
    }
}
