<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Catalog;

use Src\Battle\Domain\Enums\TipoEfectoAliado;
use Src\Battle\Domain\ValueObjects\EfectoAliado;

/**
 * Catálogo de movimientos que pueden usarse sobre un aliado vivo.
 *
 * Es el único predicado de modo aliado: no modifica `esAutodirigido()` ni el
 * objetivo declarado en base de datos. Cualquier slug desconocido devuelve
 * `null`, por lo que las sesiones antiguas (`slug = ''`) quedan intactas.
 */
final class CatalogoEfectosAliado
{
    public static function para(string $slug): ?EfectoAliado
    {
        return match ($slug) {
            'sand-tomb' => new EfectoAliado(TipoEfectoAliado::ESCUDO_FISICO),
            'fire-spin' => new EfectoAliado(TipoEfectoAliado::ESCUDO_ESPECIAL),
            'whirlpool' => new EfectoAliado(TipoEfectoAliado::AQUA_RING, turnos: 5),
            'recover' => new EfectoAliado(TipoEfectoAliado::CURAR_PS),
            'nasty-plot',
            'paz_mental',
            'swords-dance',
            'dragon-dance',
            'bulk-up' => new EfectoAliado(TipoEfectoAliado::BUFF_STATS),
            'roost',
            'morning-sun',
            'synthesis',
            'moonlight',
            'soft-boiled',
            'milk-drink',
            'slack-off',
            'heal-order',
            'shore-up' => new EfectoAliado(TipoEfectoAliado::CURAR_PS),
            'agility',
            'rock-polish',
            'autotomize',
            'harden',
            'withdraw',
            'rizo_defensa',
            'barrier',
            'acid-armor',
            'iron-defense',
            'shelter',
            'cotton-guard',
            'amnesia',
            'charge',
            'tail-glow',
            'cosmic-power',
            'defend-order',
            'magnetic-flux',
            'sharpen',
            'hone-claws',
            'work-up',
            'coil',
            'quiver-dance',
            'shift-gear',
            'geomancy',
            'victory-dance' => new EfectoAliado(TipoEfectoAliado::BUFF_STATS),
            'refresh' => new EfectoAliado(TipoEfectoAliado::CURAR_ESTADO),
            'focus-energy',
            'laser-focus' => new EfectoAliado(TipoEfectoAliado::CRITICO_MEJORADO, turnos: 5),
            default => null,
        };
    }

    public static function admite(string $slug): bool
    {
        return self::para($slug) !== null;
    }
}
