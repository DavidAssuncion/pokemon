<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\Effects\ColeccionEfectos;

/**
 * Estado de supresión de habilidades de un combatiente.
 *
 * Guarda los efectos de habilidad retirados por Jugo Gástrico para poder
 * restaurarlos al expirar. Los objetos no entran aquí: se identifican por
 * `CatalogoObjetos` y permanecen activos.
 */
final class EstadoHabilidad
{
    private bool $anulada = false;

    private bool $anuladaPorGas = false;

    private ColeccionEfectos $guardados;

    public function __construct()
    {
        $this->guardados = new ColeccionEfectos();
    }

    public function anulada(): bool
    {
        return $this->anulada;
    }

    public function anuladaPorGas(): bool
    {
        return $this->anuladaPorGas;
    }

    public function guardados(): ColeccionEfectos
    {
        return $this->guardados;
    }

    public function marcarAnulada(ColeccionEfectos $guardados, bool $porGas = false): void
    {
        $this->anulada = true;
        $this->anuladaPorGas = $porGas;
        $this->guardados = $guardados;
    }

    public function restaurar(): ColeccionEfectos
    {
        $guardados = $this->guardados;
        $this->guardados = new ColeccionEfectos();
        $this->anulada = false;
        $this->anuladaPorGas = false;

        return $guardados;
    }

    public function restaurarDesde(bool $anulada, ColeccionEfectos $guardados, bool $anuladaPorGas = false): void
    {
        $this->anulada = $anulada;
        $this->guardados = $guardados;
        $this->anuladaPorGas = $anulada && $anuladaPorGas;
    }
}
