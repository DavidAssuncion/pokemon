<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\ModoInteraccionHabilidad;

/**
 * Interacción genérica con habilidades: anular (Jugo Gástrico), copiar
 * (Imitación), intercambiar (Intercambio) o ignorar (Rompemoldes).
 */
class EjecutorInteraccionHabilidad extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::INTERACCION_HABILIDAD;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $modo = $accion->move->perfil->modoHabilidad;
        if ($modo === null) {
            return;
        }

        $turnos = max(1, $accion->move->perfil->turnosHabilidad);

        match ($modo) {
            ModoInteraccionHabilidad::ANULAR => $this->anular($accion, $turnos, $battle),
            ModoInteraccionHabilidad::COPIAR => $this->copiar($accion, $battle),
            ModoInteraccionHabilidad::INTERCAMBIAR => $this->intercambiar($accion, $battle),
            ModoInteraccionHabilidad::IGNORAR => $this->ignorar($accion, $turnos, $battle),
        };
    }

    private function anular(AccionBatalla $accion, int $turnos, AgregadoBatalla $battle): void
    {
        $accion->defender->anularHabilidad($turnos);
        $battle->agregarLog("La habilidad de {$accion->defender->nombre()} queda anulada {$turnos} turnos");
    }

    private function copiar(AccionBatalla $accion, AgregadoBatalla $battle): void
    {
        $accion->attacker->copiarHabilidadDe($accion->defender);
        $battle->agregarLog("{$accion->attacker->nombre()} copia la habilidad de {$accion->defender->nombre()}");
    }

    private function intercambiar(AccionBatalla $accion, AgregadoBatalla $battle): void
    {
        $accion->attacker->intercambiarHabilidadesCon($accion->defender);
        $battle->agregarLog("{$accion->attacker->nombre()} y {$accion->defender->nombre()} intercambian habilidades");
    }

    private function ignorar(AccionBatalla $accion, int $turnos, AgregadoBatalla $battle): void
    {
        $accion->attacker->ignorarHabilidadRival($turnos);
        $battle->agregarLog("{$accion->attacker->nombre()} ignorará las habilidades rivales {$turnos} turnos");
    }
}
