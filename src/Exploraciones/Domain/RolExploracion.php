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

    /**
     * Devuelve el rol sugerido para un pokémon en función de sus capacidades
     * nucleares normalizadas: combate → COMBATIENTE, recolección → RECOLECTOR,
     * detección → RASTREADOR, supervivencia → VANGUARDIA.
     *
     * Se elige la capacidad de mayor valor. Para el desempate (empates entre
     * las capacidades máximas) se usa un orden determinista de prioridad:
     * COMBATIENTE > RECOLECTOR > RASTREADOR > VANGUARDIA, de modo que con
     * todas las capacidades iguales el resultado es COMBATIENTE.
     */
    public static function sugeridoPara(CapacidadesStats $c): self
    {
        $capacidades = [
            self::COMBATIENTE->value => $c->combate(),
            self::RECOLECTOR->value => $c->recoleccion(),
            self::RASTREADOR->value => $c->deteccion(),
            self::VANGUARDIA->value => $c->supervivencia(),
        ];

        $maximo = max($capacidades);

        foreach (self::ORDEN_SUGERIDO as $valor) {
            if ($capacidades[$valor] === $maximo) {
                return self::from($valor);
            }
        }

        return self::COMBATIENTE;
    }

    /**
     * Orden de prioridad (valores string) para el desempate de sugeridoPara().
     * COMBATIENTE > RECOLECTOR > RASTREADOR > VANGUARDIA.
     *
     * @var list<string>
     */
    private const ORDEN_SUGERIDO = [
        self::COMBATIENTE->value,
        self::RECOLECTOR->value,
        self::RASTREADOR->value,
        self::VANGUARDIA->value,
    ];

    public static function desde(string $valor): self
    {
        return self::tryFrom($valor) ?? throw new InvalidArgumentException("Rol de exploración inválido: {$valor}");
    }
}
