<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Ports;

use DateTimeImmutable;

/**
 * Puerto de tiempo del dominio.
 *
 * Aísla la obtención del instante actual para permitir relojes fijos en
 * pruebas y mantener el dominio libre de detalles de infraestructura.
 */
interface Reloj
{
    /**
     * Instante actual.
     */
    public function ahora(): DateTimeImmutable;
}
