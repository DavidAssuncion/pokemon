<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain;

use Src\Shared\Domain\PerfilIvsEvs;

/**
 * Reglas de los hábitats temporales de facciones rivales.
 *
 * - 3 oleadas por facción y 4 combates por oleada (3 reclutas + 1 jefe).
 * - Desbloqueo de facción por medallas; oleadas 2 y 3 por hitos globales.
 * - Todos los rivales tienen exactamente el nivel del jugador.
 * - IV/EV con los mismos perfiles que gimnasio, con el jefe subiendo un tier.
 */
final class ReglasHabitatTemporal
{
    public const TOTAL_OLEADAS = 3;

    public const COMBATES_POR_OLEADA = 4;

    /** Recompensa de hábitat temporal: 3× la de un entrenador normal. */
    public const MULTIPLICADOR_RECOMPENSA = 3.0;

    /**
     * Medallas de gimnasio necesarias para abrir una oleada.
     * La oleada 1 no tiene puerta propia: depende del desbloqueo de la facción.
     */
    public static function medallasRequeridasOleada(int $oleada): int
    {
        return match ($oleada) {
            2 => 9,
            3 => 15,
            default => 0,
        };
    }

    /**
     * Nivel del rival: exactamente el nivel del jugador, sin bonus.
     */
    public static function nivelRival(int $nivelJugador): int
    {
        return max(1, $nivelJugador);
    }

    /**
     * Perfil IV/EV por oleada y por condición de jefe.
     *
     * Oleada 1: reclutas TopUno; jefe TopDos.
     * Oleada 2: reclutas TopDos; jefe Completo.
     * Oleada 3: reclutas Completo; capitán Completo (tope).
     */
    public static function perfilIvsEvs(int $oleada, bool $esJefe): PerfilIvsEvs
    {
        $base = match ($oleada) {
            1 => PerfilIvsEvs::TopUno,
            2 => PerfilIvsEvs::TopDos,
            default => PerfilIvsEvs::Completo,
        };

        if (! $esJefe) {
            return $base;
        }

        return match ($base) {
            PerfilIvsEvs::TopUno => PerfilIvsEvs::TopDos,
            PerfilIvsEvs::TopDos => PerfilIvsEvs::Completo,
            default => PerfilIvsEvs::Completo,
        };
    }

    /**
     * Índice de dificultad para el perfil de movimientos del generador.
     * No altera el nivel ni el perfil IV/EV.
     */
    public static function indiceDificultad(string $slug): int
    {
        return match ($slug) {
            'rocket' => 3,
            'magma' => 6,
            'aqua' => 9,
            'galaxia' => 12,
            'skull' => 15,
            default => 1,
        };
    }
}
