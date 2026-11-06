<?php

declare(strict_types=1);

namespace App\Livewire\Presenters;

use App\Livewire\Combate;

/**
 * Compone el contrato de vista de `livewire.combate` a partir del estado
 * público del componente. Sin estado ni acceso a dominio.
 */
final class PresentadorVistaCombate
{
    /**
     * @return array<string, mixed>
     */
    public function datos(Combate $componente): array
    {
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
        );

        $vista['movesResumen'] = MovesPreviewPresenter::resumen($componente->currentMoves);

        return $vista;
    }
}
