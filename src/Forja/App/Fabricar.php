<?php

declare(strict_types=1);

namespace Src\Forja\App;

use Illuminate\Support\Facades\DB;
use Src\Forja\App\DataTransferObjects\ResultadoFabricacion;
use Src\Forja\Domain\CatalogoRecetas;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;
use Src\Shared\Domain\Ports\InventarioJugador;

/**
 * Caso de uso de fabricación. En esta fase se produce una unidad por acción
 * dentro de una única transacción.
 */
final readonly class Fabricar
{
    public function __construct(
        private InventarioJugador $inventario,
    ) {
    }

    /**
     * @throws \Src\Forja\Domain\Exceptions\RecetaNoExiste
     * @throws ViolacionReglaNegocio
     */
    public function handle(int $userId, string $recetaClave, int $cantidad = 1): ResultadoFabricacion
    {
        $receta = CatalogoRecetas::receta($recetaClave);

        if ($cantidad !== 1) {
            throw new ViolacionReglaNegocio('Solo se puede fabricar una unidad por acción.');
        }

        return DB::transaction(function () use ($userId, $receta): ResultadoFabricacion {
            $this->inventario->consumirVarios(
                $userId,
                $receta->ingredientes,
                'Faltan materiales para fabricar',
            );

            $this->inventario->incrementar(
                $userId,
                $receta->resultadoClave,
                $receta->resultadoCantidad,
            );

            $claves = $receta->ingredientes->claves()->toList();
            $claves[] = $receta->resultadoClave;

            $saldos = $this->inventario->saldos(
                $userId,
                new StringCollection(array_values(array_unique($claves))),
            );

            return new ResultadoFabricacion(
                recetaClave: $receta->clave,
                resultadoClave: $receta->resultadoClave,
                cantidad: $receta->resultadoCantidad,
                saldos: $saldos,
            );
        });
    }
}
