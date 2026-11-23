<?php

declare(strict_types=1);

namespace Src\Avistamientos\Infra;

use Src\Avistamientos\App\ObtenerSeleccionVigente;
use Src\Avistamientos\Domain\DataTransferObjects\SeleccionVigente;
use Src\Shared\Domain\Ports\FactorAparicion;

/**
 * Implementación del puerto `FactorAparicion` para el evento de avistamientos.
 *
 * La selección se resuelve una sola vez por instancia (binding `scoped`): la
 * generación de un pool consulta el factor una vez por especie y no repite
 * lecturas ni escrituras. Devuelve el factor solo para el par hábitat/familia
 * de la ventana; para cualquier otro par devuelve 1.0.
 */
final class FactorAparicionAvistamientos implements FactorAparicion
{
    private ?SeleccionVigente $seleccion = null;

    private bool $resuelta = false;

    public function __construct(
        private readonly ObtenerSeleccionVigente $obtenerSeleccion,
    ) {
    }

    public function factor(int $habitatId, ?int $evolutionChainId): float
    {
        if ($evolutionChainId === null) {
            return 1.0;
        }

        if (! $this->resuelta) {
            $this->seleccion = ($this->obtenerSeleccion)();
            $this->resuelta = true;
        }

        $registro = $this->seleccion?->registro;

        if ($registro === null
            || $registro->habitatId !== $habitatId
            || $registro->evolutionChainId !== $evolutionChainId
        ) {
            return 1.0;
        }

        return $registro->factor;
    }
}
