<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Agregado;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * Gas Reactivo: activación, anulación de habilidades y sincronización cuando
 * el portador deja de estar vivo. Sin estado: el id del portador permanece
 * en el agregado y se pasa/retorna en cada llamada.
 */
final class GestorGasReactivo
{
    public function activar(
        AgregadoBatalla $batalla,
        Combatiente $portador,
        ?string $gasReactivoId,
    ): string {
        if ($gasReactivoId === $portador->id()) {
            return $portador->id();
        }

        if ($gasReactivoId !== null) {
            $batalla->sincronizarGasReactivo();
        }

        foreach ($batalla->turnManager()->allCombatants() as $combatiente) {
            if ($combatiente !== $portador) {
                $combatiente->anularHabilidad(999, true);
            }
        }

        $batalla->agregarLog("¡{$portador->nombre()} libera Gas Reactivo!");

        return $portador->id();
    }

    public function sincronizar(AgregadoBatalla $batalla, ?string $gasReactivoId): ?string
    {
        if ($gasReactivoId === null) {
            return null;
        }

        $portador = $this->buscarPorId($batalla, $gasReactivoId);
        if ($portador !== null && $portador->estaVivo()) {
            return $gasReactivoId;
        }

        foreach ($batalla->turnManager()->allCombatants() as $combatiente) {
            // No restaurar habilidades anuladas por otra fuente (Jugo Gástrico).
            if ($combatiente->habilidadAnuladaPorGas()) {
                $combatiente->restaurarHabilidad();
            }
        }
        $batalla->agregarLog('El Gas Reactivo se disipa');

        return null;
    }

    private function buscarPorId(AgregadoBatalla $batalla, string $id): ?Combatiente
    {
        foreach ($batalla->turnManager()->allCombatants() as $combatiente) {
            if ($combatiente->id() === $id) {
                return $combatiente;
            }
        }

        return null;
    }
}
