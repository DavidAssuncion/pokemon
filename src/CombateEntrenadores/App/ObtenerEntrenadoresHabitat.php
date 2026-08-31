<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use Src\CombateEntrenadores\Domain\Repositories\EntrenadorLogRepositoryInterface;

/**
 * Devuelve el listado de entrenadores de un hábitat (3 por nivel) con su
 * estado de desbloqueo del día y la vista de su equipo (idéntico al que se
 * generará en el combate, gracias a la semilla determinista por fecha).
 */
class ObtenerEntrenadoresHabitat
{
    public function __construct(
        private readonly GeneradorEquipoEntrenador $generadorEquipo,
        private readonly EntrenadorLogRepositoryInterface $logRepository,
    ) {
    }

    /**
     * @return array<int, list<array{indice: int, desbloqueado: bool, pokemon: list<array{id: int, nombre: string, icon: string, posicion: string}>}>>
     */
    public function obtener(int $habitatId, int $userId, string $fecha): array
    {
        $niveles = [];

        for ($nivel = 1; $nivel <= 3; $nivel++) {
            $entrenadores = [];

            for ($indice = 1; $indice <= 3; $indice++) {
                $equipo = $this->generadorEquipo->generar($habitatId, $nivel, $indice, $fecha);

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
                    'pokemon' => array_map(
                        fn ($datos) => [
                            'id' => $datos->speciesId,
                            'nombre' => $datos->nombre,
                            'icon' => $datos->speciesId > 0 ? "/images/iconos_webp/{$datos->speciesId}.webp" : '/images/iconos_webp/0.webp',
                            'posicion' => $datos->posicion->value,
                        ],
                        $equipo,
                    ),
                ];
            }

            $niveles[$nivel] = $entrenadores;
        }

        return $niveles;
    }
}
