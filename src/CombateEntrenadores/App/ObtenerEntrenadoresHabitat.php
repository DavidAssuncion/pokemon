<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use Src\CombateEntrenadores\Domain\Collections\EntrenadoresNivelCollection;
use Src\CombateEntrenadores\Domain\Collections\EstadoEntrenadorCollection;
use Src\CombateEntrenadores\Domain\DataTransferObjects\EntrenadoresNivel;
use Src\CombateEntrenadores\Domain\DataTransferObjects\EstadoEntrenador;
use Src\CombateEntrenadores\Domain\Repositories\EntrenadorLogRepositoryInterface;

/**
 * Devuelve el listado de entrenadores de un hábitat (3 por nivel) con su
 * estado de desbloqueo del día. No revela el equipo (pokémon) de los
 * entrenadores.
 */
final class ObtenerEntrenadoresHabitat
{
    public function __construct(
        private readonly EntrenadorLogRepositoryInterface $logRepository,
    ) {
    }

    public function obtener(int $habitatId, int $userId, string $fecha): EntrenadoresNivelCollection
    {
        $niveles = new EntrenadoresNivelCollection();

        for ($nivel = 1; $nivel <= 3; $nivel++) {
            $entrenadores = new EstadoEntrenadorCollection();

            for ($indice = 1; $indice <= 3; $indice++) {
                $desbloqueado = ! $this->logRepository->haGanadoHoy(
                    $userId,
                    $habitatId,
                    $nivel,
                    $indice,
                    $fecha,
                );

                $entrenadores->add(new EstadoEntrenador(
                    indice: $indice,
                    desbloqueado: $desbloqueado,
                ));
            }

            $niveles->add(new EntrenadoresNivel(
                nivel: $nivel,
                entrenadores: $entrenadores,
            ));
        }

        return $niveles;
    }
}
