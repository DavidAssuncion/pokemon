<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Enums\TipoClima;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Calcula el daño por clima al final de la ronda para un combatiente.
 * Granizo: 6.25% HP a los que NO son tipo HIELO.
 * Tormenta arena: 6.25% HP a los que NO son ROCA/TIERRA/ACERO.
 */
class CalculadorDañoClima
{
    /** @var TipoPokemon[] */
    private const INMUNES_GRANIZO = [TipoPokemon::HIELO];

    /** @var TipoPokemon[] */
    private const INMUNES_TORMENTA_ARENA = [TipoPokemon::ROCA, TipoPokemon::TIERRA, TipoPokemon::ACERO];

    public function calcular(Combatiente $c, TipoClima $weather): float
    {
        if (! $c->estaVivo()) {
            return 0;
        }

        return match ($weather) {
            TipoClima::GRANIZO => $this->dañoSiNoInmune($c, self::INMUNES_GRANIZO),
            TipoClima::TORMENTA_ARENA => $this->dañoSiNoInmune($c, self::INMUNES_TORMENTA_ARENA),
            default => 0,
        };
    }

    /**
     * @param  TipoPokemon[]  $tiposInmunes
     */
    private function dañoSiNoInmune(Combatiente $c, array $tiposInmunes): float
    {
        foreach ($c->pokemon()->tiposCollection() as $tipo) {
            if (in_array($tipo, $tiposInmunes, true)) {
                return 0;
            }
        }

        return max(1, $c->pokemon()->battleStats()->hp * 0.0625);
    }
}