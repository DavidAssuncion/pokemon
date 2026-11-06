<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Ejecucion;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Effects\Movimiento\ColeccionEjecutoresMovimiento;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\FlagMovimiento;

/**
 * Evalúa las guardas que pueden abortar un movimiento antes de calcular daño:
 * humedad, terreno requerido, objetivo dormido, inmunidad de flags, telepatía
 * y daño diferido.
 */
final class EvaluadorPrecondicionesMovimiento
{
    public function __construct(
        private readonly ColeccionEjecutoresMovimiento $ejecutores,
    ) {
    }

    /**
     * Devuelve true cuando el movimiento debe abortar sin daño. Los logs y los
     * efectos de daño diferido se aplican en el mismo orden original.
     */
    public function bloquear(AccionBatalla $accion, ?AgregadoBatalla $battle): bool
    {
        return $this->bloqueadaPorHumedad($accion, $battle)
            || $this->bloqueadaPorTerreno($accion, $battle)
            || $this->bloqueadoPorObjetivoDormido($accion, $battle)
            || $this->inmuneAFlag($accion, $battle)
            || $this->bloqueadaPorTelepatia($accion, $battle)
            || $this->aplicarDanoDiferido($accion, $battle);
    }

    private function bloqueadaPorHumedad(AccionBatalla $accion, ?AgregadoBatalla $battle): bool
    {
        $perfil = $accion->move->perfil;

        if ($battle === null
            || ! $accion->defender->tieneEfecto('humedad')
            || ! $perfil->autodestruccion) {
            return false;
        }

        $battle->agregarLog("¡La humedad impide que {$accion->attacker->nombre()} use {$accion->move->nombre}!");

        return true;
    }

    private function bloqueadaPorTerreno(AccionBatalla $accion, ?AgregadoBatalla $battle): bool
    {
        return $battle !== null
            && $accion->move->perfil->fallaSinTerreno
            && ! $accion->terreno->esActivo();
    }

    private function bloqueadoPorObjetivoDormido(AccionBatalla $accion, ?AgregadoBatalla $battle): bool
    {
        if ($battle === null
            || ! $accion->move->perfil->requiereObjetivoDormido
            || $accion->defender->estado() === EstadoPokemon::SLEEP) {
            return false;
        }

        $battle->agregarLog("{$accion->move->nombre} falla porque {$accion->defender->nombre()} no está dormido");

        return true;
    }

    private function inmuneAFlag(AccionBatalla $accion, ?AgregadoBatalla $battle): bool
    {
        foreach ([FlagMovimiento::BALA, FlagMovimiento::SONIDO, FlagMovimiento::POLVO] as $flag) {
            if ($accion->move->flags->tiene($flag) && $accion->defender->esInmuneAFlag($flag)) {
                $battle?->agregarLog("{$accion->defender->nombre()} es inmune a {$accion->move->nombre}");

                return true;
            }
        }

        return false;
    }

    private function bloqueadaPorTelepatia(AccionBatalla $accion, ?AgregadoBatalla $battle): bool
    {
        if ($battle === null
            || ! $accion->defender->tieneEfecto('telepatia')
            || $battle->equipoDe($accion->attacker) !== $battle->equipoDe($accion->defender)) {
            return false;
        }

        $battle->agregarLog("{$accion->defender->nombre()} evita el ataque de su aliado por Telepatía");

        return true;
    }

    private function aplicarDanoDiferido(AccionBatalla $accion, ?AgregadoBatalla $battle): bool
    {
        $perfil = $accion->move->perfil;

        if ($battle === null || $perfil->turnosDanoDiferido <= 0) {
            return false;
        }

        $this->ejecutores->ejecutar(
            $perfil->claveEfecto ?? ClaveEfectoMovimiento::DANO_DIFERIDO,
            $accion,
            0.0,
            $battle,
        );

        return true;
    }

    /**
     * Absorbe el movimiento si el defensor es inmune al tipo efectivo.
     * Usa el perfil original del movimiento, no el ajustado por efectos.
     */
    public function inmuneATipo(AccionBatalla $accion, ?AgregadoBatalla $battle): bool
    {
        $perfilMovimiento = $accion->move->perfil;

        if ($perfilMovimiento->formulaDano !== 'normal'
            || $perfilMovimiento->danoPorcentajeHpObjetivo > 0.0
            || $perfilMovimiento->danoPorcentajeHpAtacante > 0.0) {
            return false;
        }

        $inmunidad = $accion->attacker->ignoraHabilidadRival()
            ? null
            : $accion->defender->inmunidadTipo($accion->attacker->tipoEfectivoMovimiento($accion));

        if ($inmunidad === null) {
            return false;
        }

        if ($battle !== null) {
            $inmunidad->alAbsorber(
                $accion->defender,
                $accion->attacker->tipoEfectivoMovimiento($accion),
                $battle,
            );
        }

        $accion->attacker->romperCombo();

        return true;
    }
}
