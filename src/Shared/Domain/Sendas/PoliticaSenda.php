<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Sendas;

use Src\Shared\Tipos\TipoPokemon;

/**
 * Traduce los beneficios de Senda a factores multiplicativos de combate.
 *
 * N4 aporta 0,10 y N5 0,20, de forma absoluta (N5 = 1,20 / 0,80, no 1,21).
 */
final class PoliticaSenda
{
    public static function factorDanoInfligido(MapaSendaJugador $mapa, TipoPokemon $tipo): float
    {
        return 1.0 + $mapa->bonoDano($tipo);
    }

    public static function factorDanoRecibido(MapaSendaJugador $mapa, TipoPokemon $tipo): float
    {
        return 1.0 - $mapa->bonoDefensa($tipo);
    }

    /**
     * @param  TipoPokemon[]  $tipos
     */
    public static function factorCaptura(MapaSendaJugador $mapa, array $tipos): float
    {
        return 1.0 + $mapa->bonoCaptura($tipos);
    }

    public static function factorCaramelo(MapaSendaJugador $mapa, TipoPokemon $tipo): float
    {
        return 1.0 + $mapa->bonoCaramelo($tipo);
    }
}
