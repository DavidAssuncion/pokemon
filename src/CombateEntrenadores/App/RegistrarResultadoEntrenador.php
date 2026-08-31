<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use Src\CombateEntrenadores\Domain\Repositories\EntrenadorLogRepositoryInterface;

/**
 * Registra el resultado de un combate contra un entrenador (upsert por día).
 * Con won=true el entrenador queda bloqueado el resto del día.
 */
class RegistrarResultadoEntrenador
{
    public function __construct(
        private readonly EntrenadorLogRepositoryInterface $logRepository,
    ) {
    }

    public function registrar(
        int $habitatId,
        int $nivel,
        int $trainerIndex,
        int $userId,
        string $fecha,
        bool $won,
    ): void {
        $this->logRepository->registrarResultado($userId, $habitatId, $nivel, $trainerIndex, $won, $fecha);
    }
}
