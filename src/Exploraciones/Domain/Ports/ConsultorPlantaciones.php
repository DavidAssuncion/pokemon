<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Ports;

use Src\Shared\Domain\ValueObjects\MapaFactores;

/**
 * Puerto de lectura de las plantaciones de la granja del jugador.
 *
 * El dominio de Exploraciones no conoce el modelo de Granjas: recibe un mapa
 * de factores ya resuelto con claves `baya:{slug}` => factor multiplicativo de
 * peso. Un mapa vacío deja el botín idéntico al de F1.
 */
interface ConsultorPlantaciones
{
    /**
     * @return MapaFactores clave `baya:{slug}` => factor (>1.0; 1.0 implícito)
     */
    public function factoresPara(int $userId, int $habitatId): MapaFactores;
}
