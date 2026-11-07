<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Catalog\CatalogoEfectosAliado;

/**
 * Clasifica acciones de la IA distinguiendo el apoyo sobre un aliado del
 * ataque/estado sobre el propio actor o sobre un enemigo.
 *
 * Es el único predicado que decide si una acción debe evaluarse como soporte,
 * por lo que evita que un movimiento del catálogo apuntado a un enemigo se
 * trate como cura o escudo.
 */
final class ClasificadorAccionIA
{
    public static function esApoyo(AgregadoBatalla $battle, AccionBatalla $accion): bool
    {
        $equipoDefensor = $battle->equipoDe($accion->defender);

        return $accion->defender->id() !== $accion->attacker->id()
            && $equipoDefensor !== null
            && $equipoDefensor === $battle->equipoDe($accion->attacker)
            && CatalogoEfectosAliado::admite($accion->move->slug);
    }
}
