<?php

declare(strict_types=1);

namespace App\Livewire\Presenters;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Chain\ManejadorSTAB;
use Src\Battle\Domain\Collections\CambiosStatsCollection;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Presentador de presentación: arma los datos de vista de los movimientos
 * del combatiente activo contra un objetivo concreto. Produce exactamente el
 * array que consume `moves-panel.blade.php` ($currentMoves).
 *
 * No acoplado a Livewire: métodos puros, sin estado, con parámetros tipados
 * y retorno tipado.
 */
final class MovesPreviewPresenter
{
    /**
     * @return array<int, array{
     *     nombre: string,
     *     tipo: int,
     *     potencia: int,
     *     categoria: string,
     *     daño: float,
     *     efectividad: float,
     *     stab: bool,
     *     directo: bool,
     *     statusEffect: string,
     *     selfStatChanges: array<int, array{stat: string, factor: float}>,
     *     targetStatChanges: array<int, array{stat: string, factor: float}>,
     * }>
     */
    public static function para(Combatiente $actor, AgregadoBatalla $battle, Combatiente $target): array
    {
        $previews = [];

        foreach ($actor->pokemon()->moves() as $move) {
            $previews[] = self::previewDe($actor, $battle, $target, $move);
        }

        return $previews;
    }

    /**
     * @return array{
     *     nombre: string,
     *     tipo: int,
     *     potencia: int,
     *     categoria: string,
     *     daño: float,
     *     efectividad: float,
     *     stab: bool,
     *     directo: bool,
     *     statusEffect: string,
     *     selfStatChanges: array<int, array{stat: string, factor: float}>,
     *     targetStatChanges: array<int, array{stat: string, factor: float}>,
     * }
     */
    private static function previewDe(
        Combatiente $actor,
        AgregadoBatalla $battle,
        Combatiente $target,
        MovimientoBatalla $move
    ): array {
        $defenderTeam = self::defenderTeam($battle, $target);

        $action = new AccionBatalla(
            attacker: $actor,
            defender: $target,
            move: $move,
            defenderTeamHasVanguard: $defenderTeam->tieneVanguardiaViva(),
            weather: $battle->weather(),
            isPreview: true,
        );

        return [
            'nombre' => $move->nombre,
            'tipo' => $move->tipo->value,
            'potencia' => $move->potencia,
            'categoria' => $move->categoria->value,
            'daño' => $battle->damageChain()->calculate($action),
            'efectividad' => $move->tipo->effectiveness($target->pokemon()),
            'stab' => ManejadorSTAB::tieneStab($actor, $move),
            'directo' => $actor->obtenerPorcentajeDanioDirecto() > 0,
            'statusEffect' => $move->statusEffect->value,
            'selfStatChanges' => self::cambiosParaArray($move->selfStatChanges),
            'targetStatChanges' => self::cambiosParaArray($move->targetStatChanges),
        ];
    }

    /**
     * @return array<int, array{stat: string, factor: float}>
     */
    private static function cambiosParaArray(CambiosStatsCollection $cambios): array
    {
        $resultado = [];
        foreach ($cambios as $cambio) {
            $resultado[] = ['stat' => $cambio->statValue(), 'factor' => $cambio->factor];
        }

        return $resultado;
    }

    private static function defenderTeam(AgregadoBatalla $battle, Combatiente $defender): EquipoBatalla
    {
        return $battle->team1->findCombatant($defender) !== null
            ? $battle->team1
            : $battle->team2;
    }
}
