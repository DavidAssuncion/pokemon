<?php

declare(strict_types=1);

namespace App\Support;

use Src\Exploraciones\Domain\Ports\ConsultorPlantaciones;
use Src\Granjas\Infra\Plantaciones\ConsultorPlantacionesEloquent;

/**
 * Composition root del consultor de plantaciones para construcciones
 * manuales del handler. En producción el contenedor inyecta el binding.
 */
final class ConsultorPlantacionesFactory
{
    public static function crear(): ConsultorPlantaciones
    {
        return new ConsultorPlantacionesEloquent();
    }
}
