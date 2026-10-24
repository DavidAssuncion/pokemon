<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Repositories;

use Src\Battle\Domain\Collections\MovimientoCollection;
use Src\Battle\Domain\Movimiento;

/**
 * Contrato de lectura del catálogo de movimientos. La escritura queda para la
 * tarea de carga/administración.
 */
interface MovimientoRepository
{
    /**
     * No devuelve null: lanza MovimientoNoExiste si no existe.
     */
    public function obtenerPorId(int $id): Movimiento;

    /**
     * No devuelve null: lanza MovimientoNoExiste si no existe.
     */
    public function obtenerPorSlug(string $slug): Movimiento;

    public function todos(): MovimientoCollection;
}
