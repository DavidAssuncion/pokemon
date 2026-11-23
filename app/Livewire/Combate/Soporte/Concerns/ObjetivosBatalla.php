<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte\Concerns;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ObjetivoMovimiento;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Helpers compartidos por los gestores de preparación y prioridades.
 *
 * Antes vivían duplicados en `GestorPreparacionBatalla` y
 * `GestorPrioridadesBatalla`; el comportamiento es el mismo.
 */
trait ObjetivosBatalla
{
    private function objetivoForzadoDesafio(AgregadoBatalla $battle, Combatiente $actor): ?Combatiente
    {
        $desafio = $actor->condicion(ClaveCondicion::DESAFIO);
        if ($desafio === null || $desafio->objetivoForzadoId === '') {
            return null;
        }

        $objetivo = $battle->equipoRivalDe($actor)?->findCombatantById($desafio->objetivoForzadoId);

        return $objetivo !== null && $objetivo->estaVivo() ? $objetivo : null;
    }

    private function esAutodirigido(MovimientoBatalla $movimiento): bool
    {
        return $movimiento->esAutodirigido()
            || $movimiento->objetivo === ObjetivoMovimiento::ALL;
    }

    /**
     * Normaliza el valor de un mapa público de Livewire: cualquier valor que
     * no sea una cadena se considera vacío.
     */
    private function textoMapa(mixed $valor): string
    {
        return is_string($valor) ? $valor : '';
    }
}
