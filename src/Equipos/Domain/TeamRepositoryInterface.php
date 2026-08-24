<?php

declare(strict_types=1);

namespace Src\Equipos\Domain;

interface TeamRepositoryInterface
{
    /** @return TeamAggregate[] */
    public function obtenerTodos(): array;

    public function obtenerPorId(int $id): ?TeamAggregate;

    public function guardar(TeamAggregate $team): void;

    public function eliminar(int $id): void;
}
