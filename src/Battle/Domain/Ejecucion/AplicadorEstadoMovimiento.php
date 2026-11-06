<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Ejecucion;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Collections\CombatientesCollection;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Danio\GestorEfectosMovimiento;
use Src\Battle\Domain\Danio\ResolutorObjetivosDanio;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoEspejoMagico;
use Src\Battle\Domain\Enums\FlagMovimiento;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Aplica estado, cambios de stats y efectos de estado del movimiento,
 * incluidos Espejo Mágico y cuerpo áureo.
 */
final class AplicadorEstadoMovimiento
{
    public function __construct(
        private readonly GestorEfectosMovimiento $gestorEfectos,
    ) {
    }

    /**
     * Aplica el estado secundario del movimiento al objetivo.
     *
     * @return bool true solo si el estado se escribió en el objetivo declarado;
     *              false en cuerpo áureo, inmunidades de flag y Espejo Mágico
     *              (que redirige el estado al atacante).
     */
    public function aplicarEstado(
        Combatiente $objetivo,
        MovimientoBatalla $movimiento,
        ?AgregadoBatalla $battle = null,
        ?Combatiente $atacante = null,
    ): bool {
        if ($movimiento->esEstado() && $objetivo->tieneEfecto('cuerpo_aureo')) {
            return false;
        }

        foreach ([FlagMovimiento::BALA, FlagMovimiento::SONIDO, FlagMovimiento::POLVO] as $flag) {
            if ($movimiento->flags->tiene($flag) && $objetivo->esInmuneAFlag($flag)) {
                return false;
            }
        }

        if ($atacante !== null && $battle !== null) {
            foreach ($battle->equipoDe($objetivo)?->efectos()->all() ?? [] as $efecto) {
                if ($efecto instanceof EfectoEquipoEspejoMagico && $efecto->estaActivo()) {
                    $this->gestorEfectos->aplicarEstado($atacante, $movimiento, $battle);
                    $battle->agregarLog("¡Espejo Mágico devuelve {$movimiento->nombre} a {$atacante->nombre()}!");

                    return false;
                }
            }
        }

        return $this->gestorEfectos->aplicarEstado($objetivo, $movimiento, $battle);
    }

    /**
     * Aplica cambios de estadísticas del movimiento al actor y al objetivo.
     */
    public function aplicarStatChanges(Combatiente $actor, Combatiente $objetivo, MovimientoBatalla $movimiento): void
    {
        $this->gestorEfectos->aplicarStatChanges($actor, $objetivo, $movimiento);
    }

    /**
     * Aplica el estado principal y los cambios de estadísticas del movimiento
     * a todos los objetivos resueltos (fila, allAdjacent, etc.).
     *
     * @param  CombatientesCollection|null  $objetivos  Objetivos realmente alcanzados;
     *        null resuelve todos los del movimiento, colección vacía no aplica a ninguno.
     */
    public function aplicarEstadoYStatChanges(
        AccionBatalla $accion,
        ?AgregadoBatalla $battle = null,
        ?CombatientesCollection $objetivos = null,
    ): void {
        if ($objetivos === null) {
            $objetivos = $battle !== null
                ? ResolutorObjetivosDanio::resolver($accion, $battle)
                : null;
            $objetivos ??= new CombatientesCollection([$accion->defender]);
        }

        $objetivos = $objetivos->filter(
            fn (Combatiente $objetivo): bool => ! ($accion->move->esEstado() && $objetivo->tieneEfecto('cuerpo_aureo')),
        );

        // Sin objetivos alcanzados el movimiento falló (precisión, inmunidad,
        // protección…): no debe aplicar estados ni cambios de stats, sobre todo
        // los auto-cambios de movimientos como Lluevehojas o Fuerza Bruta.
        if ($objetivos->isEmpty()) {
            return;
        }

        foreach ($objetivos as $objetivo) {
            $this->aplicarEstado($objetivo, $accion->move, $battle, $accion->attacker);
        }

        $this->gestorEfectos->aplicarStatChangesAMuchos($accion->attacker, $objetivos->all(), $accion->move);
    }
}
