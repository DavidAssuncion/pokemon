<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use InvalidArgumentException;
use Src\Shared\Domain\ReglasEquipo;

/**
 * Política económica de una expedición.
 *
 * Encapsula el factor global sobre las recompensas y el reparto de EXP entre
 * la cuenta del jugador y el destino (equipo legacy o reclutado individual).
 * Dominio puro: sin Eloquent, HTTP ni Blade.
 *
 * - `legado()` conserva el factor 1.0 y reparte la EXP 50 % para la cuenta y
 *   50 % entre los 3 miembros del equipo (combates de ruta y flujos anteriores
 *   a la economía individual).
 * - `individual()` aplica la Fase 1: factor 0.5 sobre EXP/caramelos y reparto
 *   50 % cuenta / 50 % reclutado (un único destino).
 *
 * Ambas políticas reparten exactamente el 100 % del pool: la fracción de cuenta
 * más la de destino debe sumar 1. El factor se aplica UNA sola vez en dominio;
 * persistencia y presentación no vuelven a descontar.
 */
final readonly class EconomiaRecompensas
{
    /** Fracción de EXP que recibe la cuenta en la economía individual. */
    private const FRACCION_CUENTA_INDIVIDUAL = 0.5;

    /** Tolerancia al comparar la suma de fracciones con 1.0. */
    private const TOLERANCIA_FRACCIONES = 1e-9;

    public function __construct(
        public float $factor,
        public float $fraccionCuenta,
        public float $fraccionDestino,
        public int $miembrosDestino,
    ) {
        if (abs(($this->fraccionCuenta + $this->fraccionDestino) - 1.0) > self::TOLERANCIA_FRACCIONES) {
            throw new InvalidArgumentException(sprintf(
                'Las fracciones de cuenta (%.6f) y destino (%.6f) deben sumar 1.0.',
                $this->fraccionCuenta,
                $this->fraccionDestino,
            ));
        }

        if ($this->factor > 1.0) {
            throw new InvalidArgumentException(sprintf(
                'El factor económico no puede superar 1.0; recibido %.6f.',
                $this->factor,
            ));
        }

        if ($this->miembrosDestino < 1) {
            throw new InvalidArgumentException(sprintf(
                'La economía debe tener al menos un miembro destino; recibido %d.',
                $this->miembrosDestino,
            ));
        }
    }

    /** Economía histórica: factor 1.0 y reparto 50 % cuenta / 50 % entre 3 miembros. */
    public static function legado(): self
    {
        return new self(
            factor: 1.0,
            fraccionCuenta: 0.5,
            fraccionDestino: 0.5,
            miembrosDestino: ReglasEquipo::MIEMBROS,
        );
    }

    /** Economía individual de Fase 1: 50 % de recompensas y EXP 50/50. */
    public static function individual(): self
    {
        return new self(
            factor: ReglasExploracion::FACTOR_RECOMPENSAS,
            fraccionCuenta: self::FRACCION_CUENTA_INDIVIDUAL,
            fraccionDestino: ReglasExploracion::FRACCION_EXP_INDIVIDUAL,
            miembrosDestino: 1,
        );
    }

    /**
     * Indica si la política es la individual (EXP repartida 50/50 con un único
     * destino) frente a la legada (50 % entre 3 miembros).
     */
    public function esIndividual(): bool
    {
        return $this->fraccionDestino === ReglasExploracion::FRACCION_EXP_INDIVIDUAL
            && $this->fraccionCuenta === self::FRACCION_CUENTA_INDIVIDUAL
            && $this->miembrosDestino === 1;
    }
}
