<?php

declare(strict_types=1);

namespace Src\Mazmorras\App;

use App\Models\Habitat;
use Src\Mazmorras\Domain\Collections\PisoMazmorraCollection;
use Src\Mazmorras\Domain\ConfiguracionMazmorra;
use Src\Mazmorras\Domain\DataTransferObjects\PisoMazmorra;
use Src\Mazmorras\Domain\DataTransferObjects\ResultadoPisosMazmorra;
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
    ) {
    }

    public function obtener(int $habitatId, int $userId): ResultadoPisosMazmorra
    {
        $habitat = Habitat::query()->find($habitatId);
        $config = $habitat !== null ? ConfiguracionMazmorra::desdeJson($habitat->mazmorra) : null;

        if ($config === null) {
            return new ResultadoPisosMazmorra(new PisoMazmorraCollection());
        }

        $actual = $this->repositorio->pisoActual($userId, $habitatId) ?? 1;
        $total = $config->totalPisos();

        $pisos = new PisoMazmorraCollection();
        for ($piso = 1; $piso <= min($actual, $total); $piso++) {
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
