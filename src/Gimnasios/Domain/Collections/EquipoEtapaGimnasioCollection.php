<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\Collections;

use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;
use Src\Shared\Domain\Collection;

/**
 * Equipos de gimnasio indexados por número de etapa (1-4).
 *
 * La clave es la etapa, por eso el constructor conserva las claves en lugar
 * de reindexar como hace el Collection base.
 */
final class EquipoEtapaGimnasioCollection extends Collection
{
    public string $type = EquipoEtapaGimnasio::class;

    /**
     * @param  array<int, EquipoEtapaGimnasio>  $etapas
     */
    public function __construct(array $etapas = [])
    {
        parent::__construct([]);

        foreach ($etapas as $etapa => $equipo) {
            $this->validateType($equipo);
            $this->items[$etapa] = $equipo;
        }
    }

    public function en(int $etapa): ?EquipoEtapaGimnasio
    {
        $equipo = $this->items[$etapa] ?? null;

        return $equipo instanceof EquipoEtapaGimnasio ? $equipo : null;
    }

    public function con(int $etapa, EquipoEtapaGimnasio $equipo): self
    {
        $etapas = $this->items;
        $etapas[$etapa] = $equipo;
        ksort($etapas);

        return new self($etapas);
    }

    /**
     * @return array<int, EquipoEtapaGimnasio>
     */
    public function etapas(): array
    {
        return $this->items;
    }
}
