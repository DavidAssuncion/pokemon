<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

/**
 * Capacidades de un pokémon reclutado para exploración, calculadas a partir
 * de sus stats base y niveles (dominio puro).
 */
final class CapacidadesStats
{
    public const UMBRAL_COMPETENTE = 1.0;

    public const UMBRAL_EXPERTO = 2.0;

    public const UMBRAL_MAESTRO = 3.5;

    public function __construct(
        public readonly int $hp,
        public readonly int $atk,
        public readonly int $def,
        public readonly int $spAtk,
        public readonly int $spDef,
        public readonly int $speed,
        public readonly int $nivelPokemon,
        public readonly int $nivelEntrenador,
    ) {
    }

    /**
     * Capacidad de combate (atk + spAtk + def + spDef + niveles).
     */
    public function combate(): float
    {
        return 0.25 * $this->atk
            + 0.25 * $this->spAtk
            + 0.25 * $this->def
            + 0.25 * $this->spDef
            + $this->nivelPokemon
            + $this->nivelEntrenador;
    }

    /**
     * Capacidad de detección (speed + spDef + niveles).
     */
    public function deteccion(): float
    {
        return 0.60 * $this->speed
            + 0.40 * $this->spDef
            + $this->nivelPokemon
            + $this->nivelEntrenador;
    }

    /**
     * Capacidad de recolección (spDef + speed + hp + def + niveles).
     */
    public function recoleccion(): float
    {
        return 0.25 * $this->spDef
            + 0.25 * $this->speed
            + 0.25 * $this->hp
            + 0.25 * $this->def
            + $this->nivelPokemon
            + $this->nivelEntrenador;
    }

    /**
     * Capacidad de supervivencia (hp + def + spDef + niveles).
     */
    public function supervivencia(): float
    {
        return 0.33 * $this->hp
            + 0.33 * $this->def
            + 0.33 * $this->spDef
            + $this->nivelPokemon
            + $this->nivelEntrenador;
    }

    /**
     * Capacidad de exploración (speed + spDef + def + supervivencia + niveles).
     */
    public function exploracion(): float
    {
        return 0.40 * $this->speed
            + 0.20 * $this->spDef
            + 0.20 * $this->def
            + 0.20 * $this->supervivencia()
            + $this->nivelPokemon
            + $this->nivelEntrenador;
    }

    /**
     * Capacidad de movilidad (speed + niveles).
     */
    public function movilidad(): float
    {
        return 1.00 * $this->speed
            + $this->nivelPokemon
            + $this->nivelEntrenador;
    }

    /**
     * Rango de la capacidad pedida comparando su valor con la dificultad del
     * hábitat: NOVATO < 1×, COMPETENTE ≥ 1×, EXPERTO ≥ 2×, MAESTRO ≥ 3.5×.
     *
     * @throws \InvalidArgumentException si la capacidad no existe.
     */
    public function rangoDe(string $capacidad, int $dificultad): RangoCapacidad
    {
        $valor = match ($capacidad) {
            'combate' => $this->combate(),
            'deteccion' => $this->deteccion(),
            'recoleccion' => $this->recoleccion(),
            'supervivencia' => $this->supervivencia(),
            'exploracion' => $this->exploracion(),
            'movilidad' => $this->movilidad(),
            default => throw new \InvalidArgumentException("Capacidad desconocida: {$capacidad}"),
        };

        if ($valor >= $dificultad * self::UMBRAL_MAESTRO) {
            return RangoCapacidad::MAESTRO;
        }

        if ($valor >= $dificultad * self::UMBRAL_EXPERTO) {
            return RangoCapacidad::EXPERTO;
        }

        if ($valor >= $dificultad * self::UMBRAL_COMPETENTE) {
            return RangoCapacidad::COMPETENTE;
        }

        return RangoCapacidad::NOVATO;
    }

    /**
     * Rango de detección para la dificultad dada.
     */
    public function rangoDeteccion(int $dificultad): RangoCapacidad
    {
        return $this->rangoDe('deteccion', $dificultad);
    }

    /**
     * Bonus de caramelos de recolección: nivel de rango de recolección.
     */
    public function bonusCaramelosRecoleccion(int $dificultad): int
    {
        return $this->rangoDe('recoleccion', $dificultad)->nivel();
    }

    /**
     * Bonus de eventos por tick: nivel de rango de exploración.
     */
    public function bonusEventosExploracion(int $dificultad): int
    {
        return $this->rangoDe('exploracion', $dificultad)->nivel();
    }

    /**
     * Multiplicador de recuperación por descanso (supervivencia).
     */
    public function multiplicadorRecuperacion(int $dificultad): float
    {
        return match ($this->rangoDe('supervivencia', $dificultad)) {
            RangoCapacidad::NOVATO => 1.0,
            RangoCapacidad::COMPETENTE => 1.25,
            RangoCapacidad::EXPERTO => 1.5,
            RangoCapacidad::MAESTRO => 1.75,
        };
    }

    /**
     * Reducción del intervalo entre encuentros (movilidad).
     */
    public function reduccionIntervaloMovilidad(int $dificultad): float
    {
        return match ($this->rangoDe('movilidad', $dificultad)) {
            RangoCapacidad::NOVATO => 0.0,
            RangoCapacidad::COMPETENTE => 0.10,
            RangoCapacidad::EXPERTO => 0.25,
            RangoCapacidad::MAESTRO => 0.40,
        };
    }

    /**
     * Bonus de daño en combate (combate).
     */
    public function bonusDanoCombate(int $dificultad): float
    {
        return match ($this->rangoDe('combate', $dificultad)) {
            RangoCapacidad::NOVATO => 1.0,
            RangoCapacidad::COMPETENTE => 1.05,
            RangoCapacidad::EXPERTO => 1.10,
            RangoCapacidad::MAESTRO => 1.15,
        };
    }

    /**
     * Detección MAESTRO: la emboscada se evita automáticamente sin coste.
     */
    public function deteccionAutoEvasion(int $dificultad): bool
    {
        return $this->rangoDeteccion($dificultad) === RangoCapacidad::MAESTRO;
    }

    /**
     * Detección ≥ COMPETENTE: se permiten emboscadas en los eventos.
     */
    public function permitirEmboscadas(int $dificultad): bool
    {
        return $this->rangoDeteccion($dificultad)->nivel() >= RangoCapacidad::COMPETENTE->nivel();
    }

    /**
     * Detección ≥ EXPERTO: se permiten encuentros excepcionales.
     */
    public function permitirExcepcionales(int $dificultad): bool
    {
        return $this->rangoDeteccion($dificultad)->nivel() >= RangoCapacidad::EXPERTO->nivel();
    }

    /**
     * @return array{combate: float, deteccion: float, recoleccion: float, supervivencia: float, exploracion: float, movilidad: float}
     */
    public function todas(): array
    {
        return [
            'combate' => $this->combate(),
            'deteccion' => $this->deteccion(),
            'recoleccion' => $this->recoleccion(),
            'supervivencia' => $this->supervivencia(),
            'exploracion' => $this->exploracion(),
            'movilidad' => $this->movilidad(),
        ];
    }
}
