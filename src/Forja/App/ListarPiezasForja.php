<?php

declare(strict_types=1);

namespace Src\Forja\App;

use Src\Forja\Domain\Piezas\CatalogoPiezas;
use Src\Forja\Domain\Piezas\Collections\RecetasPiezaCollection;
use Src\Forja\Domain\Ports\DefinicionesPieza;
use Src\Forja\Domain\Ports\NidosConstruidos;

/**
 * Compone las recetas de pieza del jugador a partir de sus nidos construidos.
 *
 * No calcula stock ni motivos: eso queda en `ListarRecetas`, que hace una única
 * lectura de saldos para las recetas estáticas y las de pieza.
 */
final readonly class ListarPiezasForja
{
    public function __construct(
        private NidosConstruidos $nidos,
        private DefinicionesPieza $lector,
    ) {
    }

    public function handle(int $userId): RecetasPiezaCollection
    {
        $nidos = $this->nidos->paraJugador($userId);

        if ($nidos->isEmpty()) {
            return new RecetasPiezaCollection();
        }

        return CatalogoPiezas::recetas(
            $this->lector->definiciones($nidos),
            $nidos,
        );
    }
}
