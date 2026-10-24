<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\Effects\InterfazEfecto;

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

    /** @var list<InterfazEfecto> */
    private array $guardados = [];

    public function anulada(): bool
    {
        return $this->anulada;
    }

    public function anuladaPorGas(): bool
    {
        return $this->anuladaPorGas;
    }

    /**
     * @return list<InterfazEfecto>
     */
    public function guardados(): array
    {
        return $this->guardados;
    }

    /**
     * @param  list<InterfazEfecto>  $guardados
     */
    public function marcarAnulada(array $guardados, bool $porGas = false): void
    {
        $this->anulada = true;
        $this->anuladaPorGas = $porGas;
        $this->guardados = $guardados;
    }

    /**
     * @return list<InterfazEfecto>
     */
    public function restaurar(): array
    {
        $guardados = $this->guardados;
        $this->guardados = [];
        $this->anulada = false;
        $this->anuladaPorGas = false;

        return $guardados;
    }

    /**
     * @param  list<InterfazEfecto>  $guardados
     */
    public function restaurarDesde(bool $anulada, array $guardados, bool $anuladaPorGas = false): void
    {
        $this->anulada = $anulada;
        $this->guardados = $guardados;
        $this->anuladaPorGas = $anulada && $anuladaPorGas;
    }
}
