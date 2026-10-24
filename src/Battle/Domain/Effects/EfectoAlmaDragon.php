<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Effects\Capacidades\BonusStab;
use Src\Battle\Domain\Effects\Capacidades\TransformaTipoMovimiento;
use Src\Battle\Domain\ReglasBatalla;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Alma Dragón: los ataques de tipo Normal pasan a Dragón y reciben STAB.
 */
class EfectoAlmaDragon implements InterfazEfecto, TransformaTipoMovimiento, BonusStab
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function tipoEfectivo(AccionBatalla $accion): ?TipoPokemon
    {
        return $accion->move->tipo === TipoPokemon::NORMAL ? TipoPokemon::DRAGON : null;
    }

    public function multiplicadorStab(AccionBatalla $accion): float
    {
        if ($accion->move->tipo !== TipoPokemon::NORMAL) {
            return 1.0;
        }

        foreach ($accion->attacker->tiposDefensivos() as $tipo) {
            if ($tipo === TipoPokemon::DRAGON) {
                return 1.0;
            }
        }

        return ReglasBatalla::BONUS_STAB;
    }
}
