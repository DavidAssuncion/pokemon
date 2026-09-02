<?php

declare(strict_types=1);

namespace Src\Gimnasios\App;

use App\Models\Team;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\EquipoBatalla;
use Src\CombateEntrenadores\App\ConstruirEquipoJugador;
use Src\Gimnasios\Domain\CatalogoGimnasios;
use Src\Gimnasios\Domain\EvsRangoEntrenador;
use Src\Gimnasios\Domain\Exceptions\GimnasioBloqueado;
use Src\Gimnasios\Domain\Exceptions\GimnasioCompletado;
use Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface;
use Src\Shared\Domain\EscaladorNivelRival;

/**
 * Crea una batalla contra un gimnasio y la guarda en sesión.
 * Valida nivel mínimo, que el gimnasio no esté completado, y que la etapa
 * actual sea la que se va a combatir.
 *
 * El jugador es siempre team1; el rival (generado del catálogo) es team2.
 */
final class IniciarCombateGimnasio
{
    private const SESSION_VERSION = 8;

    public function __construct(
        private readonly CatalogoGimnasios $catalogo,
        private readonly GymProgressRepositoryInterface $repositorio,
        private readonly EscaladorNivelRival $escalador,
        private readonly GeneradorPokemonGimnasio $generador,
        private readonly ConstruirEquipoJugador $construirEquipoJugador,
    ) {
    }

    /**
     * @param  array<int, string>  $formacion  posición por slot del equipo jugador
     */
    public function iniciar(
        string $gymSlug,
        int $teamId,
        int $userId,
        int $nivelJugador,
        array $formacion = [],
    ): string {
        $gimnasio = $this->catalogo->porSlugOrFail($gymSlug);

        if ($this->repositorio->esCompletado($userId, $gymSlug)) {
            throw new GimnasioCompletado();
        }

        if ($nivelJugador < $gimnasio->nivelMinimo) {
            throw new GimnasioBloqueado($gimnasio->nivelMinimo);
        }

        $etapa = $this->repositorio->obtenerProgreso($userId, $gymSlug) ?? 1;

        $nivelRival = $this->escalador->escalar($gimnasio->nivelMinimo, $nivelJugador);

        // Etapas 1-3 (entrenadores): 64/64; etapa 4 (líder): 128/64
        [$evPrincipal, $evResto] = $etapa === 4
            ? [EvsRangoEntrenador::LIDER_PRINCIPAL, EvsRangoEntrenador::LIDER_RESTO]
            : [EvsRangoEntrenador::GIMNASIO_PRINCIPAL, EvsRangoEntrenador::GIMNASIO_RESTO];

        $equipo = Team::with('members.reclutado.pokemon.stats', 'members.reclutado.pokemon.types')
            ->findOrFail($teamId);

        $datosJugador = $this->construirEquipoJugador->desdeEquipo($equipo, $formacion, $nivelJugador);

        $equipoEtapa = $gimnasio->equipoEtapa($etapa);
        $datosRival = $equipoEtapa !== null
            ? $this->generador->generar($equipoEtapa, $nivelRival, $evPrincipal, $evResto)
            : [];

        $team1 = EquipoBatalla::fromData($datosJugador, $equipo->name);
        $team2 = EquipoBatalla::fromData($datosRival, $gimnasio->nombreEtapa($etapa));

        $batalla = new AgregadoBatalla($team1, $team2);
        $batalla->triggerBattleStartEffects();

        $battleId = 'battle_gimnasio_'.uniqid();

        session()->put($battleId, self::SESSION_VERSION.'|'.serialize($batalla));
        session()->put($battleId.'_meta', [
            'tipo' => 'gimnasio',
            'gym_id' => $gymSlug,
            'stage' => $etapa,
            'nivel_rival' => $nivelRival,
            'user_id' => $userId,
            'team_id' => $teamId,
        ]);

        return $battleId;
    }
}
