<?php

declare(strict_types=1);

namespace Src\Reclutamiento\Domain;

interface ReclutamientoRepositoryInterface
{
    /** @return ReclutadoEntity[] */
    public function obtenerTodos(): array;

    public function obtenerPorId(int $id): ?ReclutadoEntity;

    public function obtenerPorPokemonId(int $pokemonId): ?ReclutadoEntity;

    public function guardar(ReclutadoEntity $reclutado): void;

    public function eliminar(int $id): void;
}
