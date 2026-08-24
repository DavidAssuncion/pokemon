<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Aplica modificadores climáticos al daño:
 * - Sequía:   Fuego +25%, Agua -25%
 * - Diluvio:  Agua +25%, Fuego -25%
 * - Niebla:   Siniestro/Fantasma/Psíquico +25%
 * - Granizo:  HIELO +25% SpDef (reduce daño especial)
 * - Tormenta: ROCA/TIERRA/ACERO +25% Def (reduce daño físico)
 * - Turbulencias: Dragón/Volador +25%
 */
class ManejadorClima extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        $weather = $action->weather;
        if ($weather === TipoClima::NONE) {
            return $daño;
        }

        $tipoMovimiento = $action->move->tipo;

        $multiplicador = match ($weather) {
            TipoClima::SEQUIA => $this->multiplicadorSequia($tipoMovimiento),
            TipoClima::DILUVIO => $this->multiplicadorDiluvio($tipoMovimiento),
            TipoClima::NIEBLA => $this->multiplicadorNiebla($tipoMovimiento),
            TipoClima::GRANIZO => $this->multiplicadorGranizo($tipoMovimiento, $action),
            TipoClima::TORMENTA_ARENA => $this->multiplicadorTormenta($tipoMovimiento, $action),
            TipoClima::TURBULENCIAS => $this->multiplicadorTurbulencias($tipoMovimiento),
            default => 1.0,
        };

        return $daño * $multiplicador;
    }

    private function multiplicadorSequia(TipoPokemon $tipo): float
    {
        return match ($tipo) {
            TipoPokemon::FUEGO => 1.25,
            TipoPokemon::AGUA => 0.75,
            default => 1.0,
        };
    }

    private function multiplicadorDiluvio(TipoPokemon $tipo): float
    {
        return match ($tipo) {
            TipoPokemon::AGUA => 1.25,
            TipoPokemon::FUEGO => 0.75,
            default => 1.0,
        };
    }

    private function multiplicadorNiebla(TipoPokemon $tipo): float
    {
        return match ($tipo) {
            TipoPokemon::SINIESTRO, TipoPokemon::FANTASMA, TipoPokemon::PSIQUICO => 1.25,
            default => 1.0,
        };
    }

    private function multiplicadorGranizo(TipoPokemon $tipo, AccionBatalla $action): float
    {
        if ($tipo === TipoPokemon::HIELO && $action->move->esEspecial()) {
            // HIELO gana +25% SpDef → el ataque especial hace -20% (1/1.25 = 0.8)
            return 0.80;
        }

        return 1.0;
    }

    private function multiplicadorTormenta(TipoPokemon $tipo, AccionBatalla $action): float
    {
        if ($action->move->esFisico()) {
            $defensor = $action->defender;
            foreach ($defensor->pokemon()->tiposCollection() as $tipoDef) {
                if (in_array($tipoDef, [TipoPokemon::ROCA, TipoPokemon::TIERRA, TipoPokemon::ACERO], true)) {
                    // +25% Def → ataque físico hace -20% (1/1.25 = 0.8)
                    return 0.80;
                }
            }
        }

        return 1.0;
    }

    private function multiplicadorTurbulencias(TipoPokemon $tipo): float
    {
        return match ($tipo) {
            TipoPokemon::DRAGON, TipoPokemon::VOLADOR => 1.25,
            default => 1.0,
        };
    }
}
