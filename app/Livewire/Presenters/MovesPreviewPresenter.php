<?php

declare(strict_types=1);

namespace App\Livewire\Presenters;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\BarrerasVidaManager;
use Src\Battle\Domain\Chain\ManejadorSTAB;
use Src\Battle\Domain\Collections\CambiosStatsCollection;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
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
     *     danoHp: float,
     *     danoBarrera: float,
     *     barreraLabel: string,
     *     letal: bool,
     *     statusEffect: string,
     *     selfStatChanges: array<int, array{stat: string, factor: float}>,
     *     targetStatChanges: array<int, array{stat: string, factor: float}>,
     *     golpes: int,
     *     golpesMin: int,
     *     golpesMax: int,
     *     disponible: bool,
     *     motivoNoDisponible: string|null,
     *     cargaActual: int,
     *     cargaMaxima: int,
     *     usosRestantes: int,
     *     usosMaximos: int,
     *     comboActual: int,
     *     comboMultiplicador: float,
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
     *     danoHp: float,
     *     danoBarrera: float,
     *     barreraLabel: string,
     *     letal: bool,
     *     statusEffect: string,
     *     selfStatChanges: array<int, array{stat: string, factor: float}>,
     *     targetStatChanges: array<int, array{stat: string, factor: float}>,
     *     golpes: int,
     *     golpesMin: int,
     *     golpesMax: int,
     *     disponible: bool,
     *     motivoNoDisponible: string|null,
     *     cargaActual: int,
     *     cargaMaxima: int,
     *     usosRestantes: int,
     *     usosMaximos: int,
     *     comboActual: int,
     *     comboMultiplicador: float,
     * }
     */
    private static function previewDe(
        Combatiente $actor,
        AgregadoBatalla $battle,
        Combatiente $target,
        MovimientoBatalla $move
    ): array {
        $defenderTeam = self::defenderTeam($battle, $target);
        $categoria = $move->esEstado() ? CategoriaMovimiento::ESTADO : $move->categoria;

        $action = new AccionBatalla(
            attacker: $actor,
            defender: $target,
            move: $move,
            defenderTeamHasVanguard: $defenderTeam->tieneVanguardiaViva(),
            weather: $battle->weather(),
            isPreview: true,
            equipoDefensor: $defenderTeam,
            terreno: $battle->terreno(),
        );

        $daño = $battle->damageChain()->calculate($action);
        $directPct = min(1.0, $actor->obtenerPorcentajeDanioDirecto() + $move->perfil->danoDirectoFraccion);
        $barreraActual = $move->esEspecial()
            ? $target->defensaEspHpActual()
            : $target->defensaHpActual();
        $desglose = (new BarrerasVidaManager())->desglosarDaño(
            $daño,
            $barreraActual,
            $directPct,
            $move->perfil->multiplicadorDanoEscudo * $actor->multiplicadorDanioEscudo($action),
            $move->perfil->multiplicadorDanoSalud,
        );
        $tipoEfectivo = $actor->tipoEfectivoMovimiento($action);
        $ajusteGolpes = $actor->ajustarGolpes($action);
        $golpes = $move->perfil->golpesMax;
        $golpesMin = $move->perfil->golpesMin;
        if ($ajusteGolpes !== null) {
            $golpes = $ajusteGolpes->golpes;
            $golpesMin = $ajusteGolpes->golpes;
        }

        return [
            'nombre' => $move->nombre,
            'tipo' => $tipoEfectivo->value,
            'potencia' => $move->potencia,
            'categoria' => $categoria->value,
            'daño' => $daño,
            'danoHp' => $desglose->danoHp,
            'danoBarrera' => $desglose->danoEscudo,
            'barreraLabel' => $move->esEspecial() ? 'DEF.ESP' : 'DEF',
            'letal' => $daño > 0 && $desglose->danoHp >= $target->hpActual(),
            'efectividad' => $move->esEstado() ? 1.0 : $target->efectividadContra($tipoEfectivo),
            'stab' => ManejadorSTAB::tieneStabDeTipo($actor, $tipoEfectivo),
            'directo' => ! $move->esEstado() && $directPct > 0,
            'statusEffect' => $move->statusEffect->value,
            'selfStatChanges' => self::cambiosParaArray($move->selfStatChanges),
            'targetStatChanges' => self::cambiosParaArray($move->targetStatChanges),
            'golpes' => $golpes,
            'golpesMin' => $golpesMin,
            'golpesMax' => $golpes,
            'disponible' => $actor->movimientoDisponible($move),
            'motivoNoDisponible' => $actor->motivoMovimientoNoDisponible($move),
            'cargaActual' => $actor->cargaMovimiento($move),
            'cargaMaxima' => $move->perfil->cargaMaxima,
            'usosRestantes' => $actor->usosRestantes($move),
            'usosMaximos' => $move->perfil->usosMaximos,
            'comboActual' => $actor->comboMovimiento() === $move->nombre ? $actor->combo() : 0,
            'comboMultiplicador' => $actor->comboMultiplicadorPara($move),
        ];
    }

    /**
     * @return array<int, array{stat: string, factor: float}>
     */
    private static function cambiosParaArray(CambiosStatsCollection $cambios): array
    {
        $resultado = [];
        foreach ($cambios as $cambio) {
            $resultado[] = ['stat' => $cambio->statValue(), 'factor' => $cambio->factorTemporal()];
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
