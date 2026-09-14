<?php

declare(strict_types=1);

namespace Src\Gimnasios\App;

use App\Models\Team;
use App\Support\CreadorBatallaSesion;
use Src\CombateEntrenadores\App\ConstruirEquipoJugador;
use Src\Gimnasios\Domain\EvsRangoEntrenador;
use Src\Gimnasios\Domain\Exceptions\GimnasioBloqueado;
use Src\Gimnasios\Domain\Exceptions\GimnasioCompletado;
use Src\Gimnasios\Domain\Repositories\GymCatalogoRepositoryInterface;
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
    public function __construct(
        private readonly GymCatalogoRepositoryInterface $catalogo,
        private readonly GymProgressRepositoryInterface $repositorio,
        private readonly EscaladorNivelRival $escalador,
        private readonly GeneradorPokemonGimnasio $generador,
        private readonly ConstruirEquipoJugador $construirEquipoJugador,
        private readonly CreadorBatallaSesion $creadorBatalla,
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
        $gimnasio = $this->catalogo->obtenerPorSlugOrFail($gymSlug);

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

        return $this->creadorBatalla->crearYGuardar(
            datosJugador: $datosJugador,
            nombreJugador: $equipo->name,
            datosRival: $datosRival,
            nombreRival: $gimnasio->nombreEtapa($etapa),
            prefijoId: 'gimnasio',
            meta: [
                'tipo' => 'gimnasio',
                'gym_id' => $gymSlug,
                'stage' => $etapa,
                'nivel_rival' => $nivelRival,
                'user_id' => $userId,
                'team_id' => $teamId,
            ],
        );
    }
}
