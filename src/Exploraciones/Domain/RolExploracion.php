<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use InvalidArgumentException;

/**
 * Rol de un miembro del equipo en una expedición (team_members.behavior).
 *
 * D7/RF-13: VANGUARDIA, COMBATIENTE, RECOLECTOR, RASTREADOR (SOPORTE eliminado).
 * Modificadores de rol aplicados como multiplicadores/bonus sobre encuentros,
 * capacidad, EXP, caramelos, huidas y contratiempos.
 */
enum RolExploracion: string
{
    case VANGUARDIA = 'VANGUARDIA';
    case COMBATIENTE = 'COMBATIENTE';
    case RECOLECTOR = 'RECOLECTOR';
    case RASTREADOR = 'RASTREADOR';

    /**
     * Bonus de capacidad por rol (se suma a base + afinidad + sinergia).
     * Combatiente = resolución en combate (contra grupos); Vanguardia y
     * Rastreador aportan presencia; Recolector no suma capacidad.
     */
    public function bonusCapacidad(): int
    {
        return match ($this) {
            self::COMBATIENTE => 15,
            self::VANGUARDIA, self::RASTREADOR => 5,
            self::RECOLECTOR => 0,
        };
    }

    /** Modificador de encuentros: Vanguardia/Rastreador +30 %, Recolector −30 %. */
    public function multiplicadorEncuentros(): float
    {
        return match ($this) {
            self::VANGUARDIA, self::RASTREADOR => 1.3,
            self::RECOLECTOR => 0.7,
            self::COMBATIENTE => 1.0,
        };
    }

    /** Modificador de EXP: Vanguardia/Combatiente +25 %, Recolector −20 %. */
    public function multiplicadorExp(): float
    {
        return match ($this) {
            self::VANGUARDIA, self::COMBATIENTE => 1.25,
            self::RECOLECTOR => 0.8,
            self::RASTREADOR => 1.0,
        };
    }

    /** Modificador de caramelos de hallazgo: Recolector +50 %. */
    public function multiplicadorCaramelosHallazgo(): float
    {
        return match ($this) {
            self::RECOLECTOR => 1.5,
            default => 1.0,
        };
    }

    /** Probabilidad de huida del salvaje: Rastreador −50 %. */
    public function multiplicadorHuidas(): float
    {
        return match ($this) {
            self::RASTREADOR => 0.5,
            default => 1.0,
        };
    }

    /** Probabilidad de retirada: Combatiente −40 %. */
    public function multiplicadorRetirada(): float
    {
        return match ($this) {
            self::COMBATIENTE => 0.6,
            default => 1.0,
        };
    }

    /** Tiempo perdido general: Rastreador −50 %. */
    public function multiplicadorTiempoPerdido(): float
    {
        return match ($this) {
            self::RASTREADOR => 0.5,
            default => 1.0,
        };
    }

    /** Vanguardia detecta emboscadas (RF-06/RF-13: único cambio de resolución). */
    public function detectaEmboscadas(): bool
    {
        return $this === self::VANGUARDIA;
    }

    /**
     * Mitiga contratiempos: Vanguardia −50 % terreno/bloqueo, Combatiente −50 % clima.
     * (Rastreador aplica su multiplicador de tiempo perdido general en el evaluador.).
     */
    public function mitigaContratiempo(string $subtipo): bool
    {
        if ($subtipo === 'terreno' || $subtipo === 'bloqueo') {
            return $this === self::VANGUARDIA;
        }

        if ($subtipo === 'clima') {
            return $this === self::COMBATIENTE;
        }

        return false;
    }

    public static function desde(string $valor): self
    {
        return self::tryFrom($valor) ?? throw new InvalidArgumentException("Rol de exploración inválido: {$valor}");
    }
}
