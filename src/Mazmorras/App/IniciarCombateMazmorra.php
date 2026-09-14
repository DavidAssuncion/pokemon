<?php

declare(strict_types=1);

namespace Src\Mazmorras\App;

use App\Models\Habitat;
use App\Models\Team;
use App\Support\CreadorBatallaSesion;
use Src\CombateEntrenadores\App\ConstruirEquipoJugador;
use Src\Mazmorras\Domain\ConfiguracionMazmorra;
use Src\Mazmorras\Domain\Repositories\DungeonProgresoRepositoryInterface;

/**
 * Crea la batalla de un piso de mazmorra y la guarda en sesión.
 * Reglas:
 * - El hábitat debe tener mazmorra configurada con el piso.
 * - El piso debe ser el actual (los ganados no se repiten; los futuros no se
 *   saltan).
 * - Tras una derrota el piso queda en cooldown 1h (no se puede reintentar).
 *
 * El jugador es team1; el jefe ×10 (un solo combatiente) es team2.
 */
final class IniciarCombateMazmorra
{
    public function __construct(
        private readonly DungeonProgresoRepositoryInterface $repositorio,
        private readonly GeneradorJefeMazmorra $generadorJefe,
        private readonly ConstruirEquipoJugador $construirEquipoJugador,
        private readonly CreadorBatallaSesion $creadorBatalla,
    ) {
    }

    /**
     * @param  array<int, string>  $formacion  posición por slot del equipo jugador
     */
    public function iniciar(
        int $habitatId,
        int $piso,
        int $teamId,
        int $userId,
        int $nivelJugador,
        array $formacion = [],
    ): string {
        $habitat = Habitat::query()->find($habitatId);
        $config = $habitat !== null ? ConfiguracionMazmorra::desdeJson($habitat->mazmorra) : null;

        $this->validar($config, $piso, $userId, $habitatId);

        $actual = $this->repositorio->pisoActual($userId, $habitatId) ?? 1;
        if ($piso !== $actual) {
            throw new \Src\Mazmorras\Domain\Exceptions\PisoNoDisponible('Ese piso no está disponible todavía.');
        }

        $speciesId = $config?->speciesDe($piso);
        $jefe = $speciesId !== null ? $this->generadorJefe->generar($speciesId, $nivelJugador) : null;
        if ($jefe === null) {
            throw new \Src\Mazmorras\Domain\Exceptions\PisoNoDisponible('El piso no tiene un jefe configurado.');
        }

        $equipo = Team::with('members.reclutado.pokemon.stats', 'members.reclutado.pokemon.types')
            ->findOrFail($teamId);

        $datosJugador = $this->construirEquipoJugador->desdeEquipo($equipo, $formacion, $nivelJugador);

        return $this->creadorBatalla->crearYGuardar(
            datosJugador: $datosJugador,
            nombreJugador: $equipo->name,
            datosRival: [$jefe],
            nombreRival: 'Jefe del piso '.$piso,
            prefijoId: 'mazmorra',
            meta: [
                'tipo' => 'mazmorra',
                'habitat_id' => $habitatId,
                'floor' => $piso,
                'boss_species_id' => $speciesId,
                'nivel_rival' => $nivelJugador,
                'user_id' => $userId,
                'team_id' => $teamId,
            ],
        );
    }

    private function validar(?ConfiguracionMazmorra $config, int $piso, int $userId, int $habitatId): void
    {
        if ($config === null || $piso < 1 || $piso > $config->totalPisos()) {
            throw new \Src\Mazmorras\Domain\Exceptions\PisoNoDisponible('Ese piso no existe en esta mazmorra.');
        }

        if ($this->repositorio->enCooldown($userId, $habitatId, $piso)) {
            throw new \Src\Mazmorras\Domain\Exceptions\PisoNoDisponible('Este piso está en cooldown. Espera 1 hora.');
        }
    }
}
