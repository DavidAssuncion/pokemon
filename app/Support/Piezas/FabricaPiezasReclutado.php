<?php

declare(strict_types=1);

namespace App\Support\Piezas;

use App\Models\Reclutado;
use Src\Equipos\App\Piezas\ObtenerPiezas;
use Src\Forja\Domain\Piezas\AgregadorBonosPiezas;
use Src\Forja\Domain\Ports\PiezasPorEspecie;
use Src\Shared\Collections\IntCollection;

/**
 * Construye el presentador de piezas de un listado de reclutados.
 *
 * Agrupa las tres dependencias del presentador para que los controladores
 * inyecten una sola y no repitan la recolección de ids.
 */
final readonly class FabricaPiezasReclutado
{
    public function __construct(
        private ObtenerPiezas $obtenerPiezas,
        private PiezasPorEspecie $piezasPorEspecie,
        private AgregadorBonosPiezas $agregadorBonos,
    ) {
    }

    /**
     * @param  iterable<int, Reclutado>  $reclutados
     */
    public function paraListado(int $userId, iterable $reclutados): PiezasReclutado
    {
        $ids = new IntCollection();

        foreach ($reclutados as $reclutado) {
            $ids->add($reclutado->id);
        }

        return new PiezasReclutado(
            $this->obtenerPiezas,
            $this->piezasPorEspecie,
            $this->agregadorBonos,
            $userId,
            $ids,
        );
    }
}
