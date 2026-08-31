<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Models\Team;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\EquipoBatalla;
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
    private const SESSION_VERSION = 5;

    public function __construct(
        private readonly GeneradorEquipoEntrenador $generadorEquipo,
        private readonly ConstruirEquipoJugador $construirEquipoJugador,
        private readonly EntrenadorLogRepositoryInterface $logRepository,
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
        string $fecha,
        array $formacion = [],
    ): string {
        if ($this->logRepository->haGanadoHoy($userId, $habitatId, $nivel, $trainerIndex, $fecha)) {
            throw new EntrenadorDerrotadoHoy();
        }

        $equipo = Team::with('members.reclutado.pokemon.stats', 'members.reclutado.pokemon.types')
            ->findOrFail($teamId);

        $datosJugador = $this->construirEquipoJugador->desdeEquipo($equipo, $formacion);
        $datosRival = $this->generadorEquipo->generar($habitatId, $nivel, $trainerIndex, $fecha);

        $team1 = EquipoBatalla::fromData($datosJugador, $equipo->name);
        $team2 = EquipoBatalla::fromData($datosRival, "Entrenador Nivel {$nivel}");

        $batalla = new AgregadoBatalla($team1, $team2);
        $batalla->triggerBattleStartEffects();

        $battleId = 'battle_entrenador_'.uniqid();

        session()->put($battleId, self::SESSION_VERSION.'|'.serialize($batalla));
        session()->put($battleId.'_meta', [
            'habitat_id' => $habitatId,
            'nivel' => $nivel,
            'trainer_index' => $trainerIndex,
            'user_id' => $userId,
            'team_id' => $teamId,
            'fecha' => $fecha,
        ]);

        return $battleId;
    }
}
