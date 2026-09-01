<?php

declare(strict_types=1);

namespace Src\Gimnasios\App;

use Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface;

/**
 * Persiste el resultado de un combate de gimnasio en el repositorio de progreso.
 * Solo avanza si el combate fue ganado y el userId coincide con el autenticado.
 */
final class RegistrarResultadoGimnasio
{
    public function __construct(
        private readonly GymProgressRepositoryInterface $repositorio,
    ) {
    }

    /**
     * @return array{avance: bool, completado: bool, medalla: string|null}
     */
    public function registrar(
        string $gymId,
        int $etapaCompletada,
        int $userId,
        bool $won,
        int $authUserId,
        ?string $nombreMedalla = null,
    ): array {
        if (! $won || $userId !== $authUserId) {
            return ['avance' => false, 'completado' => false, 'medalla' => null];
        }

        $this->repositorio->registrarVictoria($userId, $gymId, $etapaCompletada);

        $completado = $etapaCompletada + 1 >= 5;
        $medalla = $completado ? $nombreMedalla : null;

        return ['avance' => true, 'completado' => $completado, 'medalla' => $medalla];
    }
}
