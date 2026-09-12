<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain;

use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\CombateEntrenadores\Domain\Collections\MovimientosSinteticosCollection;
use Src\CombateEntrenadores\Domain\DataTransferObjects\MovimientoSintetico;
use Src\Shared\Tipos\TipoPokemon;
use Src\Shared\Tipos\TiposCollection;

/**
 * Generación temporal de movimientos a partir de los tipos del pokémon.
 *
 * Mientras no existan datos reales de ataques/habilidades en BD, cada pokémon
 * recibe movimientos sintéticos según sus tipos:
 *
 * - Tipos no-Normal: 1 físico (60) + 1 especial (80) por tipo.
 * - Normal puro: 2 movimientos Normal de 80 y 100.
 * - Con tipos no-Normal: 2 movimientos Normal de 40 y 60.
 */
class GeneradorMovimientosTipo
{
    public function generar(TiposCollection $tipos): MovimientosSinteticosCollection
    {
        $tiposNoNormal = $tipos->filter(
            static fn (TipoPokemon $tipo): bool => $tipo !== TipoPokemon::NORMAL
        );

        $movimientos = new MovimientosSinteticosCollection();

        foreach ($tiposNoNormal as $tipo) {
            $movimientos->add(new MovimientoSintetico("Golpe {$tipo->label()}", 60, $tipo, CategoriaMovimiento::FISICO));
            $movimientos->add(new MovimientoSintetico("Ráfaga {$tipo->label()}", 80, $tipo, CategoriaMovimiento::ESPECIAL));
        }

        if ($tiposNoNormal->isEmpty()) {
            $movimientos->add(new MovimientoSintetico('Golpe Normal', 80, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO));
            $movimientos->add(new MovimientoSintetico('Ráfaga Normal', 100, TipoPokemon::NORMAL, CategoriaMovimiento::ESPECIAL));
        } else {
            $movimientos->add(new MovimientoSintetico('Golpe Normal', 40, TipoPokemon::NORMAL, CategoriaMovimiento::FISICO));
            $movimientos->add(new MovimientoSintetico('Ráfaga Normal', 60, TipoPokemon::NORMAL, CategoriaMovimiento::ESPECIAL));
        }

        return $movimientos;
    }
}
