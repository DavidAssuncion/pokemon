<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use Src\CombateEntrenadores\Domain\Repositories\EntrenadorLogRepositoryInterface;

/**
 * Devuelve el listado de entrenadores de un hábitat (3 por nivel) con su
 * estado de desbloqueo del día. No revela el equipo (pokémon) de los
 * entrenadores.
 */
class ObtenerEntrenadoresHabitat
{
    public function __construct(
        private readonly EntrenadorLogRepositoryInterface $logRepository,
    ) {
    }

    /**
     * @return array<int, list<array{indice: int, desbloqueado: bool}>>
     */
    public function obtener(int $habitatId, int $userId, string $fecha): array
    {
        $niveles = [];

        for ($nivel = 1; $nivel <= 3; $nivel++) {
            $entrenadores = [];

            for ($indice = 1; $indice <= 3; $indice++) {
                $desbloqueado = ! $this->logRepository->haGanadoHoy(
                    $userId,
                    $habitatId,
                    $nivel,
                    $indice,
                    $fecha,
                );

                $entrenadores[] = [
                    'indice' => $indice,
                    'desbloqueado' => $desbloqueado,
                ];
            }

            $niveles[$nivel] = $entrenadores;
        }

        return $niveles;
    }
}
