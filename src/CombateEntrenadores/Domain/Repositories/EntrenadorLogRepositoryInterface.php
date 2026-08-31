<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain\Repositories;

interface EntrenadorLogRepositoryInterface
{
    public function haGanadoHoy(int $userId, int $habitatId, int $level, int $trainerIndex, string $fecha): bool;

    public function registrarResultado(int $userId, int $habitatId, int $level, int $trainerIndex, bool $won, string $fecha): void;
}
