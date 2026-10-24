<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Postura que un combatiente declara antes de la ronda.
 *
 * - ATACAR: acción normal (o movimiento de prioridad declarado en preparación).
 * - GUARDIA: reduce daño y puede hacer parada perfecta.
 * - CUBRIR: redirige el 40 % del daño de un aliado hacia el portador.
 */
enum PosturaCombate: string
{
    case ATACAR = 'atacar';
    case GUARDIA = 'guardia';
    case CUBRIR = 'cubrir';

    public static function porDefecto(): self
    {
        return self::ATACAR;
    }

    public function label(): string
    {
        return match ($this) {
            self::ATACAR => 'Atacar',
            self::GUARDIA => 'Guardia',
            self::CUBRIR => 'Cubrir',
        };
    }

    public function consumeAccion(): bool
    {
        return true;
    }
}
