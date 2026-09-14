<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Models\Team;
use App\Support\CreadorBatallaSesion;
use Src\CombateEntrenadores\Domain\Exceptions\EntrenadorDerrotadoHoy;
use Src\CombateEntrenadores\Domain\Repositories\EntrenadorLogRepositoryInterface;

/**
 * Crea una batalla contra un entrenador del hábitat y la guarda en sesión
 * para que el componente Livewire de combate la cargue.
 *
 * El jugador es siempre team1; el rival (generado del pool del hábitat) es team2.
 */
class IniciarCombateEntrenador
{
    public function __construct(
        private readonly GeneradorEquipoEntrenador $generadorEquipo,
        private readonly ConstruirEquipoJugador $construirEquipoJugador,
        private readonly EntrenadorLogRepositoryInterface $logRepository,
        private readonly CreadorBatallaSesion $creadorBatalla,
    ) {
    }

    /**
     * @param  array<int, string>  $formacion  posición por slot del equipo jugador
     */
    public function iniciar(
        int $habitatId,
        int $nivel,
        int $trainerIndex,
        int $teamId,
        int $userId,
        int $nivelJugador,
        string $fecha,
        array $formacion = [],
    ): string {
        if ($this->logRepository->haGanadoHoy($userId, $habitatId, $nivel, $trainerIndex, $fecha)) {
            throw new EntrenadorDerrotadoHoy();
        }

        $equipo = Team::with('members.reclutado.pokemon.stats', 'members.reclutado.pokemon.types')
            ->findOrFail($teamId);

        $datosJugador = $this->construirEquipoJugador->desdeEquipo($equipo, $formacion, $nivelJugador);

        $nivelRival = $nivelJugador;

        $datosRival = $this->generadorEquipo->generar($habitatId, $nivel, $trainerIndex, $fecha, $nivelRival);

        return $this->creadorBatalla->crearYGuardar(
            datosJugador: $datosJugador,
            nombreJugador: $equipo->name,
            datosRival: $datosRival,
            nombreRival: "Entrenador Nivel {$nivel}",
            prefijoId: 'entrenador',
            meta: [
                'habitat_id' => $habitatId,
                'nivel' => $nivel,
                'trainer_index' => $trainerIndex,
                'user_id' => $userId,
                'team_id' => $teamId,
                'fecha' => $fecha,
            ],
        );
    }
}
