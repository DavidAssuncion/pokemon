<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Chain\ManejadorDanioBase;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;

/**
 * Servicio de dominio puro que encapsula la lógica de procesamiento
 * de estados alterados (sueño, congelación, parálisis, confusión, daño por turno).
 *
 * No guarda estado propio. Recibe valores actuales, devuelve resultados +
 * instrucciones de mutación para que el Combatiente aplique los cambios.
 */
final class EstadoManager
{
    /**
     * Verifica si el combatiente puede actuar este turno según su estado.
     * Gestiona contadores y auto-daño de confusión.
     *
     * @param callable(StatClave): float $obtenerStatEfectivo
     *
     * @return array{canAct: bool, reason: string, selfDamage: float, estadoResultado: EstadoPokemon, turnosResultado: int, hpResultado: float}
     */
    public function puedeActuar(
        EstadoPokemon $estado,
        int $turnosEstado,
        float $hpActual,
        callable $obtenerStatEfectivo,
    ): array {
        if ($estado === EstadoPokemon::NONE) {
            return $this->resultadoLimpio($estado, $turnosEstado, $hpActual);
        }

        return match ($estado) {
            EstadoPokemon::SLEEP => $this->procesarSleep($estado, $turnosEstado, $hpActual),
            EstadoPokemon::FREEZE => $this->procesarFreeze($estado, $turnosEstado, $hpActual),
            EstadoPokemon::PARALYSIS => $this->procesarParalysis($estado, $turnosEstado, $hpActual),
            EstadoPokemon::CONFUSION => $this->procesarConfusion($estado, $turnosEstado, $hpActual, $obtenerStatEfectivo),
            default => $this->resultadoLimpio($estado, $turnosEstado, $hpActual),
        };
    }

    /**
     * Calcula el daño por efecto de estado al final de la ronda.
     *
     * @return array{dano: float, hpActualNueva: float, contadorNuevo: int}
     */
    public function calcularDanoStatus(
        EstadoPokemon $estado,
        int $contadorVenenoGrave,
        float $hpMax,
        float $hpActual,
    ): array {
        if ($hpActual <= 0) {
            return ['dano' => 0.0, 'hpActualNueva' => $hpActual, 'contadorNuevo' => $contadorVenenoGrave];
        }

        if (! $estado->causaDanoPorRonda()) {
            return ['dano' => 0.0, 'hpActualNueva' => $hpActual, 'contadorNuevo' => $contadorVenenoGrave];
        }

        $daño = match ($estado) {
            EstadoPokemon::BURN => max(1, $hpMax * ReglasBatalla::FRACCION_HP_POR_RONDA),
            EstadoPokemon::POISON => max(1, $hpMax * ReglasBatalla::FRACCION_HP_VENENO),
            EstadoPokemon::BAD_POISON => max(1, $hpMax * $contadorVenenoGrave / ReglasBatalla::DIVISOR_VENENO_GRAVE),
            default => 0,
        };

        if ($daño <= 0) {
            return ['dano' => 0.0, 'hpActualNueva' => $hpActual, 'contadorNuevo' => $contadorVenenoGrave];
        }

        $hpNueva = max(0, $hpActual - $daño);
        $contadorNuevo = $estado === EstadoPokemon::BAD_POISON
            ? $contadorVenenoGrave + 1
            : $contadorVenenoGrave;

        return ['dano' => $daño, 'hpActualNueva' => $hpNueva, 'contadorNuevo' => $contadorNuevo];
    }

    // ─── Métodos privados de procesamiento por estado ─────────

    private function resultadoLimpio(
        EstadoPokemon $estado,
        int $turnosEstado,
        float $hpActual,
    ): array {
        return [
            'canAct' => true,
            'reason' => '',
            'selfDamage' => 0.0,
            'estadoResultado' => $estado,
            'turnosResultado' => $turnosEstado,
            'hpResultado' => $hpActual,
        ];
    }

    private function procesarSleep(
        EstadoPokemon $estado,
        int $turnosEstado,
        float $hpActual,
    ): array {
        if ($turnosEstado <= 0) {
            return [
                'canAct' => true,
                'reason' => 'despertó',
                'selfDamage' => 0.0,
                'estadoResultado' => EstadoPokemon::NONE,
                'turnosResultado' => $turnosEstado,
                'hpResultado' => $hpActual,
            ];
        }

        return [
            'canAct' => false,
            'reason' => 'está dormido',
            'selfDamage' => 0.0,
            'estadoResultado' => $estado,
            'turnosResultado' => $turnosEstado - 1,
            'hpResultado' => $hpActual,
        ];
    }

    private function procesarFreeze(
        EstadoPokemon $estado,
        int $turnosEstado,
        float $hpActual,
    ): array {
        if (mt_rand(1, 100) <= ReglasBatalla::PORCIENTO_DESCONGELACION) {
            return [
                'canAct' => true,
                'reason' => 'se descongeló',
                'selfDamage' => 0.0,
                'estadoResultado' => EstadoPokemon::NONE,
                'turnosResultado' => $turnosEstado,
                'hpResultado' => $hpActual,
            ];
        }

        return [
            'canAct' => false,
            'reason' => 'está congelado',
            'selfDamage' => 0.0,
            'estadoResultado' => $estado,
            'turnosResultado' => $turnosEstado,
            'hpResultado' => $hpActual,
        ];
    }

    private function procesarParalysis(
        EstadoPokemon $estado,
        int $turnosEstado,
        float $hpActual,
    ): array {
        if (mt_rand(1, 100) <= ReglasBatalla::PORCIENTO_PARALISIS) {
            return [
                'canAct' => false,
                'reason' => 'está paralizado',
                'selfDamage' => 0.0,
                'estadoResultado' => $estado,
                'turnosResultado' => $turnosEstado,
                'hpResultado' => $hpActual,
            ];
        }

        return $this->resultadoLimpio($estado, $turnosEstado, $hpActual);
    }

    /**
     * @param callable(StatClave): float $obtenerStatEfectivo
     */
    private function procesarConfusion(
        EstadoPokemon $estado,
        int $turnosEstado,
        float $hpActual,
        callable $obtenerStatEfectivo,
    ): array {
        $seAgoto = $turnosEstado <= 0;

        if ($seAgoto) {
            return [
                'canAct' => true,
                'reason' => 'salió de confusión',
                'selfDamage' => 0.0,
                'estadoResultado' => EstadoPokemon::NONE,
                'turnosResultado' => 0,
                'hpResultado' => $hpActual,
            ];
        }

        $turnosRestantes = $turnosEstado - 1;

        if (mt_rand(1, 100) <= ReglasBatalla::PORCIENTO_CONFUSION_GOLPE) {
            $atk = $obtenerStatEfectivo(StatClave::ATAQUE);
            $def = $obtenerStatEfectivo(StatClave::DEFENSA);
            $daño = max(1, ManejadorDanioBase::formulaBase(50, 40, $atk, $def));
            $hpNueva = max(0, $hpActual - $daño);

            return [
                'canAct' => false,
                'reason' => 'se golpeó por confusión',
                'selfDamage' => $daño,
                'estadoResultado' => $estado,
                'turnosResultado' => $turnosRestantes,
                'hpResultado' => $hpNueva,
            ];
        }

        return [
            'canAct' => true,
            'reason' => '',
            'selfDamage' => 0.0,
            'estadoResultado' => $estado,
            'turnosResultado' => $turnosRestantes,
            'hpResultado' => $hpActual,
        ];
    }
}
