<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Ejecucion;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Danio\AplicadorPosturaDanio;
use Src\Battle\Domain\Effects\Movimiento\ColeccionEjecutoresMovimiento;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * Resuelve el impacto completo de un movimiento contra un objetivo: guardas,
 * potencia/tipo efectivos, precisión, inmunidad, serie multi-golpe, daño,
 * combo, reacciones y cierre global (drenaje, recoil y post-impacto).
 *
 * @phpstan-type ResultadoContra array{dano: float, directPct: float, resultado: ResultadoDanio, golpes: int, curado: float, recoil: float, objetivos: list<Combatiente>}
 * @phpstan-type SerieImpacto array{danoTotal: float, danoHpTotal: float, golpes: int, ultimo: ResultadoDanio}
 */
final class ResolutorImpacto
{
    public function __construct(
        private readonly CadenaDanio $damageChain,
        private readonly AplicadorPosturaDanio $aplicadorPostura,
        private readonly CalculadorGolpes $calculadorGolpes,
        private readonly ResolutorPrecision $resolutorPrecision,
        private readonly ColeccionEjecutoresMovimiento $ejecutores,
        private readonly EvaluadorPrecondicionesMovimiento $evaluadorPrecondiciones,
        private readonly AplicadorEfectosPostImpacto $aplicadorEfectosPostImpacto,
    ) {
    }

    /**
     * @return ResultadoContra
     */
    public function ejecutarContra(
        AccionBatalla $accion,
        ?AgregadoBatalla $battle,
        bool $aplicarEfectosGlobales = true,
    ): array {
        $perfil = $accion->move->perfil;
        $directPct = min(1.0, $accion->attacker->obtenerPorcentajeDanioDirecto() + $perfil->danoDirectoFraccion);
        $accion->attacker->prepararCombo($accion->move);

        if ($accion->move->esEstado()) {
            $accion->attacker->romperCombo();
        }

        if ($this->evaluadorPrecondiciones->bloquear($accion, $battle)) {
            return $this->resultadoVacio($directPct);
        }

        $movimiento = $this->ajustarMovimiento($accion, $battle);
        $perfil = $movimiento->perfil;

        if ($battle !== null && $accion->defender !== $accion->attacker) {
            $accion->defender->dispararSerObjetivo($accion, $battle);
        }

        if (! $perfil->multiaccuracy && $this->resolutorPrecision->esquiva($accion)) {
            $accion->attacker->romperCombo();

            return $this->resultadoVacio($directPct);
        }

        if ($this->evaluadorPrecondiciones->inmuneATipo($accion, $battle)) {
            return $this->resultadoVacio($directPct);
        }

        return $this->resolverSerie($accion, $movimiento, $perfil, $directPct, $battle, $aplicarEfectosGlobales);
    }

    /**
     * @return ResultadoContra
     */
    private function resultadoVacio(float $directPct): array
    {
        return [
            'dano' => 0.0,
            'directPct' => $directPct,
            'resultado' => ResultadoDanio::sinDano(),
            'golpes' => 0,
            'curado' => 0.0,
            'recoil' => 0.0,
            'objetivos' => [],
        ];
    }

    /**
     * @return ResultadoContra
     */
    private function resolverSerie(
        AccionBatalla $accion,
        MovimientoBatalla $movimiento,
        PerfilMovimiento $perfil,
        float $directPct,
        ?AgregadoBatalla $battle,
        bool $aplicarEfectosGlobales,
    ): array {
        $serie = $this->resolver($accion, $movimiento, $perfil, $directPct, $battle);
        $danoTotal = $serie['danoTotal'];

        $globales = $this->aplicadorEfectosPostImpacto->aplicarGlobales(
            $accion,
            $danoTotal,
            $serie['danoHpTotal'],
            $aplicarEfectosGlobales,
            $battle,
        );

        $this->aplicadorEfectosPostImpacto->aplicar(
            $accion,
            $movimiento,
            $perfil,
            $danoTotal,
            $aplicarEfectosGlobales,
            $battle,
        );

        return [
            'dano' => $danoTotal,
            'directPct' => $directPct,
            'resultado' => $serie['ultimo'],
            'golpes' => $serie['golpes'],
            'curado' => $globales['curado'],
            'recoil' => $globales['recoil'],
            'objetivos' => $serie['golpes'] > 0 ? [$accion->defender] : [],
        ];
    }

    /**
     * Aplica potencia y tipo efectivos de los ejecutores del movimiento.
     */
    private function ajustarMovimiento(AccionBatalla $accion, ?AgregadoBatalla $battle): MovimientoBatalla
    {
        $movimiento = $accion->move;
        $perfil = $movimiento->perfil;

        if ($battle === null || $perfil->claveEfecto === null) {
            return $movimiento;
        }

        $potencia = $this->ejecutores->potenciaEfectiva($perfil->claveEfecto, $accion, $battle);
        if ($potencia !== null) {
            $movimiento = $movimiento->conPotencia($potencia);
            $perfil = $movimiento->perfil;
        }

        $tipo = $this->ejecutores->tipoEfectivo($perfil->claveEfecto, $accion, $battle);
        if ($tipo !== null) {
            $movimiento = $movimiento->conTipo($tipo);
        }

        return $movimiento;
    }

    /**
     * @return SerieImpacto
     */
    private function resolver(
        AccionBatalla $accion,
        MovimientoBatalla $movimiento,
        PerfilMovimiento $perfil,
        float $directPct,
        ?AgregadoBatalla $battle,
    ): array {
        $ajusteGolpes = $this->calculadorGolpes->calcular($accion, $perfil);
        $golpes = $ajusteGolpes->golpes;
        $potenciaGolpe = $golpes > 1
            ? max(1, (int) round($movimiento->potencia * $ajusteGolpes->fraccionPotencia))
            : $movimiento->potencia;
        $plan = ['golpes' => $golpes, 'potenciaGolpe' => $potenciaGolpe];

        return $this->ejecutarGolpes($accion, $movimiento, $perfil, $directPct, $battle, $plan);
    }

    /**
     * @param  array{golpes: int, potenciaGolpe: int}  $plan
     * @return SerieImpacto
     */
    private function ejecutarGolpes(
        AccionBatalla $accion,
        MovimientoBatalla $movimiento,
        PerfilMovimiento $perfil,
        float $directPct,
        ?AgregadoBatalla $battle,
        array $plan,
    ): array {
        $danoTotal = 0.0;
        $danoHpTotal = 0.0;
        $golpesRealizados = 0;
        $ultimo = ResultadoDanio::sinDano();

        for ($golpe = 0; $golpe < $plan['golpes']; $golpe++) {
            $movimientoGolpe = $plan['golpes'] > 1
                ? $movimiento->conPotencia($plan['potenciaGolpe'])
                : $movimiento;
            $resultado = $this->resolverUnGolpe($accion, $movimientoGolpe, $perfil, $directPct, $battle);

            if ($resultado === null) {
                break;
            }

            $golpesRealizados++;
            $ultimo = $resultado['resultado'];
            $danoTotal += $resultado['resultado']->danoAplicado();
            $danoHpTotal += $resultado['resultado']->danoHp;

            if (! $resultado['continuar']) {
                break;
            }
        }

        return [
            'danoTotal' => $danoTotal,
            'danoHpTotal' => $danoHpTotal,
            'golpes' => $golpesRealizados,
            'ultimo' => $ultimo,
        ];
    }

    /**
     * @return array{resultado: ResultadoDanio, continuar: bool}|null
     */
    private function resolverUnGolpe(
        AccionBatalla $accion,
        MovimientoBatalla $movimientoGolpe,
        PerfilMovimiento $perfil,
        float $directPct,
        ?AgregadoBatalla $battle,
    ): ?array {
        $accionGolpe = $this->crearAccionGolpe($accion, $movimientoGolpe, $battle);

        if ($perfil->multiaccuracy && $this->resolutorPrecision->esquiva($accionGolpe)) {
            $accion->attacker->romperCombo();

            return null;
        }

        $daño = $this->damageChain->calculate($accionGolpe);
        $resultado = $this->aplicadorPostura->aplicar($accionGolpe, $movimientoGolpe, $daño, $directPct, $perfil, $battle);

        $this->actualizarCombo($accion, $movimientoGolpe, $resultado);

        if ($battle !== null && $resultado->huboDano()) {
            $this->aplicadorEfectosPostImpacto->dispararReacciones($accion, $accionGolpe, $movimientoGolpe, $resultado, $battle);
        }

        return [
            'resultado' => $resultado,
            'continuar' => $accion->defender->estaVivo() && $accion->attacker->estaVivo() && $resultado->huboDano(),
        ];
    }

    private function crearAccionGolpe(
        AccionBatalla $accion,
        MovimientoBatalla $movimientoGolpe,
        ?AgregadoBatalla $battle,
    ): AccionBatalla {
        return new AccionBatalla(
            attacker: $accion->attacker,
            defender: $accion->defender,
            move: $movimientoGolpe,
            defenderTeamHasVanguard: $accion->defenderTeamHasVanguard,
            weather: $accion->weather,
            isPreview: $accion->isPreview,
            equipoDefensor: $battle?->equipoDe($accion->defender),
            terreno: $accion->terreno,
        );
    }

    private function actualizarCombo(
        AccionBatalla $accion,
        MovimientoBatalla $movimiento,
        ResultadoDanio $resultado,
    ): void {
        if (! $resultado->huboDano()) {
            $accion->attacker->romperCombo();

            return;
        }

        if ($movimiento->perfil->potenciaCreciente) {
            $accion->attacker->registrarGolpeCombo($movimiento);
        }
    }
}
