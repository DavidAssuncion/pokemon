<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Ejecucion;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Danio\GestorEfectosMovimiento;
use Src\Battle\Domain\Effects\Movimiento\ColeccionEjecutoresMovimiento;
use Src\Battle\Domain\Enums\TipoTerreno;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * Aplica los efectos que ocurren después del impacto: efecto de clave, rotura
 * de pantallas, eliminación de terreno, curación, coste de velocidad, efectos
 * secundarios, control y contraataque.
 */
final class AplicadorEfectosPostImpacto
{
    public function __construct(
        private readonly ColeccionEjecutoresMovimiento $ejecutores,
        private readonly AplicadorDrenaje $aplicadorDrenaje,
        private readonly AplicadorRecoil $aplicadorRecoil,
        private readonly AplicadorCuracion $aplicadorCuracion,
        private readonly AplicadorCosteVelocidad $aplicadorCosteVelocidad,
        private readonly GestorEfectosMovimiento $gestorEfectos,
    ) {
    }

    public function aplicar(
        AccionBatalla $accion,
        MovimientoBatalla $movimiento,
        PerfilMovimiento $perfil,
        float $danoTotal,
        bool $aplicarEfectosGlobales,
        ?AgregadoBatalla $battle,
    ): void {
        $this->aplicarEfectoClave($accion, $perfil, $danoTotal, $battle);
        $this->aplicarEfectosEscenario($accion, $movimiento, $danoTotal, $battle);

        if ($battle !== null) {
            $this->aplicadorCuracion->aplicar($accion, $battle);
        }

        if ($aplicarEfectosGlobales) {
            $this->aplicadorCosteVelocidad->aplicar($accion);
        }

        if ($battle !== null) {
            $this->aplicarSecundariosYControl($accion, $movimiento, $danoTotal, $battle);
        }
    }

    private function aplicarEfectoClave(
        AccionBatalla $accion,
        PerfilMovimiento $perfil,
        float $danoTotal,
        ?AgregadoBatalla $battle,
    ): void {
        if ($battle !== null && $perfil->claveEfecto !== null) {
            $this->ejecutores->ejecutar($perfil->claveEfecto, $accion, $danoTotal, $battle);
        }
    }

    private function aplicarEfectosEscenario(
        AccionBatalla $accion,
        MovimientoBatalla $movimiento,
        float $danoTotal,
        ?AgregadoBatalla $battle,
    ): void {
        if ($battle !== null && $movimiento->perfil->rompePantallas && $danoTotal > 0.0) {
            $battle->equipoDe($accion->defender)?->limpiarPantallas();
            $battle->agregarLog('¡Las pantallas del equipo rival se rompen!');
        }

        if ($battle !== null && $movimiento->perfil->eliminaTerreno && $battle->terreno()->esActivo()) {
            $battle->setTerreno(TipoTerreno::NONE);
            $battle->agregarLog('¡El terreno quedó destruido!');
        }
    }

    private function aplicarSecundariosYControl(
        AccionBatalla $accion,
        MovimientoBatalla $movimiento,
        float $danoTotal,
        AgregadoBatalla $battle,
    ): void {
        $this->gestorEfectos->aplicarSecundarios($accion, $movimiento, $battle);

        $this->gestorEfectos->aplicarControl($accion, $movimiento, $battle);

        if (! $movimiento->esEstado()
            && $accion->defender->estaVivo()
            && $accion->attacker->estaVivo()) {
            $accion->defender->contraatacar($accion, max(1.0, $danoTotal), $battle);
        }
    }

    /**
     * Aplica drenaje y recoil una sola vez, en el orden original.
     *
     * @return array{curado: float, recoil: float}
     */
    public function aplicarGlobales(
        AccionBatalla $accion,
        float $danoTotal,
        float $danoHpTotal,
        bool $aplicarEfectosGlobales,
        ?AgregadoBatalla $battle,
    ): array {
        $curado = $aplicarEfectosGlobales
            ? $this->aplicadorDrenaje->aplicar($accion, $danoHpTotal)
            : 0.0;

        $recoil = $aplicarEfectosGlobales
            ? $this->aplicadorRecoil->aplicar($accion, $danoTotal, $battle)
            : 0.0;

        return ['curado' => $curado, 'recoil' => $recoil];
    }

    /**
     * Cierra un ataque multiobjetivo: drenaje, recoil, coste de velocidad y
     * autodestrucción se aplican una sola vez, sobre el daño acumulado.
     *
     * @return array{curado: float, recoil: float}
     */
    public function cerrarMultiobjetivo(
        AccionBatalla $accion,
        float $danoTotal,
        float $danoHpTotal,
        AgregadoBatalla $battle,
    ): array {
        $curado = $this->aplicadorDrenaje->aplicar($accion, $danoHpTotal);
        $recoil = $this->aplicadorRecoil->aplicar($accion, $danoTotal, $battle);
        $this->aplicadorCosteVelocidad->aplicar($accion);
        $this->aplicarAutodestruccion($accion, $battle);

        return ['curado' => $curado, 'recoil' => $recoil];
    }

    public function dispararReacciones(
        AccionBatalla $accion,
        AccionBatalla $accionGolpe,
        MovimientoBatalla $movimiento,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        $accion->defender->dispararDanioRecibidoCompleto($accionGolpe, $resultado, $battle);

        if ($movimiento->esContacto()) {
            $accion->defender->dispararContacto($accion->attacker, $battle);
        }

        if ($resultado->rompioEscudo) {
            $accion->defender->dispararEscudoRoto($accion->attacker, $resultado, $battle);
            $accion->attacker->dispararEscudoRoto($accion->defender, $resultado, $battle);
        }

        if (! $accion->defender->estaVivo()) {
            $accion->attacker->effects()->alDebilitarRival($accion->attacker, $accion->defender, $battle);
        }
    }

    public function aplicarAutodestruccion(AccionBatalla $accion, ?AgregadoBatalla $battle): void
    {
        if (! $accion->move->perfil->autodestruccion || ! $accion->attacker->estaVivo()) {
            return;
        }

        $accion->attacker->setHpActual(0.0);
        $battle?->agregarLog("¡{$accion->attacker->nombre()} se autodestruye!");
    }
}
