<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Agregado;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\EquipoBatalla;

/**
 * Creación, límite y retirada de invocaciones. Sin estado: el contador de
 * invocaciones permanece en el agregado y el número se recibe por parámetro.
 */
final class GestorInvocaciones
{
    /**
     * Comprueba que el invocador pueda invocar y devuelve su equipo. Registra
     * el log de límite cuando corresponde.
     */
    public function equipoDisponible(
        AgregadoBatalla $batalla,
        Combatiente $invocador,
        int $maxInvocaciones,
    ): ?EquipoBatalla {
        $equipo = $batalla->equipoDe($invocador);
        if ($equipo === null || ! $invocador->estaVivo()) {
            return null;
        }

        $limite = max(1, $maxInvocaciones);
        if ($this->activas($equipo, $invocador) >= $limite) {
            $batalla->agregarLog("{$invocador->nombre()} ya alcanzó el límite de invocaciones ({$limite})");

            return null;
        }

        return $equipo;
    }

    public function preparar(
        AgregadoBatalla $batalla,
        Combatiente $invocacion,
        Combatiente $invocador,
        EquipoBatalla $equipo,
        int $turnos,
        float $fraccionHp,
        string $nombre,
        int $numero,
    ): Combatiente {
        $id = $invocador->id().'-inv-'.$numero;
        $nombreFinal = $nombre !== '' ? $nombre : "Esbirro de {$invocador->nombre()}";

        $invocacion->prepararComoInvocacion(
            id: $id,
            nombre: $nombreFinal,
            invocadoPorId: $invocador->id(),
            turnos: max(1, $turnos),
            fraccionHp: $fraccionHp,
        );
        $invocacion->setRondaActual($batalla->turnManager()->round());
        $invocacion->setClima($batalla->weather());

        $batalla->incorporarCombatiente($equipo, $invocacion, $invocador->posicion());
        $equipo->normalizarCubridores();
        $batalla->agregarLog("{$invocador->nombre()} invoca a {$nombreFinal}");

        return $invocacion;
    }

    public function retirarExpiradas(AgregadoBatalla $batalla): void
    {
        foreach ([$batalla->team1, $batalla->team2] as $equipo) {
            foreach ($equipo->combatientesCollection()->all() as $combatiente) {
                if (! $combatiente->esInvocacion()) {
                    continue;
                }

                $estabaVivo = $combatiente->estaVivo();
                $sigueActiva = $combatiente->avanzarTurnoInvocacion();
                if ($sigueActiva && $combatiente->estaVivo()) {
                    continue;
                }

                $equipo->quitarCombatiente($combatiente);
                $equipo->normalizarCubridores();
                if ($estabaVivo) {
                    $batalla->agregarLog("{$combatiente->nombre()} se desvanece");
                }
            }
        }
    }

    private function activas(EquipoBatalla $equipo, Combatiente $invocador): int
    {
        $activas = 0;
        foreach ($equipo->combatientesCollection()->vivos() as $aliado) {
            if ($aliado->esInvocacion() && $aliado->invocadoPorId() === $invocador->id()) {
                $activas++;
            }
        }

        return $activas;
    }
}
