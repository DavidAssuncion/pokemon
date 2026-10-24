<?php

declare(strict_types=1);

namespace Src\Equipos\Domain;

use Src\Equipos\Domain\Collections\TeamCollection;

interface TeamRepositoryInterface
{
    public function obtenerTodos(): TeamCollection;

    public function obtenerPorId(int $id): ?TeamAggregate;

    public function guardar(TeamAggregate $team): void;

    public function eliminar(int $id): void;
}
