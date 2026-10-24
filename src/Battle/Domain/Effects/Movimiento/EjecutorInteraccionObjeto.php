<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\ModoInteraccionObjeto;

/**
 * Interacción genérica con objetos: destruir (Desarme), robar (Ladrón),
 * intercambiar (Truco), bloquear (Embargo/Zona Mágica) y reciclar.
 */
class EjecutorInteraccionObjeto extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::INTERACCION_OBJETO;
    }

    public function potenciaEfectiva(AccionBatalla $accion, AgregadoBatalla $battle): ?int
    {
        if ($accion->move->perfil->modoObjeto !== ModoInteraccionObjeto::DESTRUIR) {
            return null;
        }

        if (! $accion->defender->itemActivo()) {
            return null;
        }

        // Desarme pega más fuerte si el objetivo lleva un objeto activo.
        return max(1, (int) round($accion->move->potencia * 1.5));
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $modo = $accion->move->perfil->modoObjeto;
        if ($modo === null) {
            return;
        }

        $turnos = max(1, $accion->move->perfil->turnosObjeto);

        match ($modo) {
            ModoInteraccionObjeto::DESTRUIR => $this->destruir($accion, $battle),
            ModoInteraccionObjeto::ROBAR => $this->robar($accion, $battle),
            ModoInteraccionObjeto::INTERCAMBIAR => $this->intercambiar($accion, $battle),
            ModoInteraccionObjeto::BLOQUEAR => $this->bloquear($accion->defender, $turnos, $battle),
            ModoInteraccionObjeto::BLOQUEAR_TODOS => $this->bloquearTodos($battle, $turnos),
            ModoInteraccionObjeto::RECICLAR => $this->reciclar($accion, $battle),
        };
    }

    private function destruir(AccionBatalla $accion, AgregadoBatalla $battle): void
    {
        $destruido = $accion->defender->destruirObjeto();
        if ($destruido === '') {
            $battle->agregarLog("{$accion->defender->nombre()} no lleva objeto que destruir");

            return;
        }

        $battle->agregarLog("¡{$accion->attacker->nombre()} destruye el objeto de {$accion->defender->nombre()}!");
    }

    private function robar(AccionBatalla $accion, AgregadoBatalla $battle): void
    {
        if (! $accion->attacker->robarObjetoDe($accion->defender)) {
            $battle->agregarLog("{$accion->attacker->nombre()} no puede robar el objeto");

            return;
        }

        $battle->agregarLog("{$accion->attacker->nombre()} roba el objeto de {$accion->defender->nombre()}");
    }

    private function intercambiar(AccionBatalla $accion, AgregadoBatalla $battle): void
    {
        if (! $accion->attacker->intercambiarObjetoCon($accion->defender)) {
            $battle->agregarLog('No hay objetos que intercambiar');

            return;
        }

        $battle->agregarLog("{$accion->attacker->nombre()} y {$accion->defender->nombre()} intercambian objetos");
    }

    private function bloquear(Combatiente $objetivo, int $turnos, AgregadoBatalla $battle): void
    {
        $objetivo->bloquearObjeto($turnos);
        $battle->agregarLog("El objeto de {$objetivo->nombre()} queda bloqueado {$turnos} turnos");
    }

    private function bloquearTodos(AgregadoBatalla $battle, int $turnos): void
    {
        foreach ($battle->turnManager()->allCombatants() as $combatiente) {
            if ($combatiente->estaVivo()) {
                $combatiente->bloquearObjeto($turnos);
            }
        }

        $battle->agregarLog("Una Zona Mágica bloquea los objetos {$turnos} turnos");
    }

    private function reciclar(AccionBatalla $accion, AgregadoBatalla $battle): void
    {
        if (! $accion->attacker->reciclarObjeto()) {
            $battle->agregarLog("{$accion->attacker->nombre()} no tiene un objeto consumido que reciclar");

            return;
        }

        $battle->agregarLog("{$accion->attacker->nombre()} recicla su objeto");
    }
}
