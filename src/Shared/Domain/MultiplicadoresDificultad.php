<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Multiplicadores de recompensa por perfil IV/EV del rival (D8 del plan v5).
 *
 * Compensan el tiempo extra que exige derrotar rivales con mejores stats:
 * - `Nulo` y `TopUno`: 1,00 / 1,00 / 1,00 (sin dificultad adicional).
 * - `TopDos`: 3,30 familia / 1,85 EV / 1,90 tipo.
 * - `Completo`: 3,35 familia / 2,80 EV / 2,40 tipo.
 *
 * Dominio puro: no conoce hábitats, rutas ni Eloquent; solo traduce el perfil
 * a sus tres coeficientes.
 */
final readonly class MultiplicadoresDificultad
{
    public function __construct(
        public PerfilIvsEvs $perfil,
    ) {
    }

    /**
     * Multiplicador de caramelos de familia (fase evolutiva).
     */
    public function familia(): float
    {
        return match ($this->perfil) {
            PerfilIvsEvs::Nulo, PerfilIvsEvs::TopUno => 1.00,
            PerfilIvsEvs::TopDos => 3.30,
            PerfilIvsEvs::Completo => 3.35,
        };
    }

    /**
     * Multiplicador de caramelos EV (effort).
     */
    public function ev(): float
    {
        return match ($this->perfil) {
            PerfilIvsEvs::Nulo, PerfilIvsEvs::TopUno => 1.00,
            PerfilIvsEvs::TopDos => 1.85,
            PerfilIvsEvs::Completo => 2.80,
        };
    }

    /**
     * Multiplicador de caramelos de tipo (EXP tipada).
     */
    public function tipo(): float
    {
        return match ($this->perfil) {
            PerfilIvsEvs::Nulo, PerfilIvsEvs::TopUno => 1.00,
            PerfilIvsEvs::TopDos => 1.90,
            PerfilIvsEvs::Completo => 2.40,
        };
    }

    /**
     * Alias de constructor: lee mejor en el flujo de aplicación.
     */
    public static function para(PerfilIvsEvs $perfil): self
    {
        return new self($perfil);
    }

    /**
     * Sin dificultad adicional: perfil Nulo → 1,00 en los tres ejes.
     */
    public static function sinDificultad(): self
    {
        return new self(PerfilIvsEvs::Nulo);
    }
}
