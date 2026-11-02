<?php

declare(strict_types=1);

namespace Src\Mazmorras\App;

use Src\Mazmorras\Domain\Collections\PisoMazmorraCollection;
use Src\Mazmorras\Domain\DataTransferObjects\PisoMazmorra;
use Src\Mazmorras\Domain\DataTransferObjects\ResultadoPisosMazmorra;
use Src\Mazmorras\Domain\ReglasMazmorra;
use Src\Mazmorras\Domain\Repositories\DungeonProgresoRepositoryInterface;

/**
 * Devuelve los pisos alcanzables de la mazmorra de un hábitat con su estado:
 * - piso < actual        → 'ganado' (no se repite)
 * - piso == actual       → 'disponible' o 'cooldown' (si derrota < 1h)
 * Los pisos futuros (> actual) no se muestran hasta alcanzarlos.
 */
final class ObtenerPisosMazmorra
{
    public function __construct(
        private readonly DungeonProgresoRepositoryInterface $repositorio,
        private readonly ObtenerConfiguracionMazmorra $obtenerConfiguracion,
    ) {
    }

    public function obtener(int $habitatId, int $userId): ResultadoPisosMazmorra
    {
        $config = $this->obtenerConfiguracion->deHabitat($habitatId);

        if ($config === null) {
            return new ResultadoPisosMazmorra(new PisoMazmorraCollection());
        }

        $actual = $this->repositorio->pisoActual($userId, $habitatId) ?? ReglasMazmorra::PISO_MIN;
        $total = $config->totalPisos();

        $pisos = new PisoMazmorraCollection();
        for ($piso = ReglasMazmorra::PISO_MIN; $piso <= min($actual, $total); $piso++) {
            $estado = $piso < $actual ? 'ganado' : $this->estadoActual($userId, $habitatId, $piso);

            $pisos->add(new PisoMazmorra(
                piso: $piso,
                nombre: 'Piso '.$piso,
                estado: $estado,
                cooldownHasta: $estado === 'cooldown'
                    ? $this->repositorio->cooldownHasta($userId, $habitatId, $piso)?->toIso8601String()
                    : null,
            ));
        }

        return new ResultadoPisosMazmorra($pisos);
    }

    private function estadoActual(int $userId, int $habitatId, int $piso): string
    {
        return $this->repositorio->enCooldown($userId, $habitatId, $piso)
            ? 'cooldown'
            : 'disponible';
    }
}
