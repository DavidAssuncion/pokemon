<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\App;

use App\Models\Team;
use App\Support\CargarMovimientosDesbloqueados;
use App\Support\CreadorBatallaSesion;
use Src\CombateEntrenadores\App\ConstruirEquipoJugador;
use Src\CombateEntrenadores\Domain\ValueObjects\FormacionEquipo;
use Src\Gimnasios\App\GeneradorPokemonGimnasio;
use Src\HabitatsTemporales\Domain\CatalogoFaccionesRivales;
use Src\HabitatsTemporales\Domain\DataTransferObjects\ProgresoHabitatTemporal;
use Src\HabitatsTemporales\Domain\Exceptions\CombateNoDisponible;
use Src\HabitatsTemporales\Domain\Exceptions\FaccionCompletada;
use Src\HabitatsTemporales\Domain\Exceptions\FaccionNoExiste;
use Src\HabitatsTemporales\Domain\Exceptions\OleadaNoDisponible;
use Src\HabitatsTemporales\Domain\ReglasHabitatTemporal;
use Src\HabitatsTemporales\Domain\Repositories\MedallasRepositoryInterface;
use Src\HabitatsTemporales\Domain\Repositories\ProgresoHabitatTemporalRepositoryInterface;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * Crea la batalla de un combate de hábitat temporal y la guarda en sesión.
 *
 * El jugador es team1; el rival es el combate actual de la oleada actual.
 * Todos los rivales usan exactamente el nivel del jugador.
 */
final class IniciarCombateHabitatTemporal
{
    public function __construct(
        private readonly CatalogoFaccionesRivales $catalogo,
        private readonly ProgresoHabitatTemporalRepositoryInterface $progresos,
        private readonly MedallasRepositoryInterface $medallas,
        private readonly GeneradorPokemonGimnasio $generadorRival,
        private readonly ConstruirEquipoJugador $construirEquipoJugador,
        private readonly CreadorBatallaSesion $creadorBatalla,
        private readonly CargarMovimientosDesbloqueados $cargarMovimientos,
    ) {
    }

    /**
     * @param  array<int, string>  $formacion  posición por slot del equipo jugador
     */
    public function iniciar(
        string $faccionSlug,
        int $oleada,
        int $combate,
        int $teamId,
        int $userId,
        int $nivelJugador,
        array $formacion = [],
    ): string {
        $faccion = $this->catalogo->porSlug($faccionSlug);
        $medallas = $this->medallas->contarCompletadas($userId);

        if ($medallas < $faccion->medallasDesbloqueo) {
            throw new FaccionNoExiste($faccionSlug);
        }

        $progreso = $this->progresos->obtener($userId, $faccionSlug)
            ?? ProgresoHabitatTemporal::inicial();

        if ($progreso->completada) {
            throw new FaccionCompletada($faccion->nombre);
        }

        if ($oleada !== $progreso->oleadaActual || $combate !== $progreso->combateActual) {
            throw new CombateNoDisponible();
        }

        $requeridas = ReglasHabitatTemporal::medallasRequeridasOleada($oleada);
        if ($medallas < $requeridas) {
            throw new OleadaNoDisponible('Ese combate no está disponible.');
        }

        $combateRival = $faccion->oleada($oleada)?->combate($combate);

        if ($combateRival === null) {
            throw new ViolacionReglaNegocio('El combate no existe en el catálogo.');
        }

        $nivelRival = ReglasHabitatTemporal::nivelRival($nivelJugador);
        $perfil = ReglasHabitatTemporal::perfilIvsEvs($oleada, $combateRival->esJefe);
        $indice = ReglasHabitatTemporal::indiceDificultad($faccionSlug);

        $datosRival = $this->generadorRival->generar(
            equipo: $combateRival->equipo,
            nivelRival: $nivelRival,
            perfil: $perfil,
            indiceGimnasio: $indice,
            esLider: $combateRival->esJefe,
            movimientosDesbloqueados: $this->cargarMovimientos->para($userId),
        );

        if ($datosRival->isEmpty()) {
            throw new ViolacionReglaNegocio('Este combate no tiene rivales disponibles.');
        }

        $equipo = Team::with('members.reclutado.pokemon.stats', 'members.reclutado.pokemon.types')
            ->findOrFail($teamId);

        $datosJugador = $this->construirEquipoJugador->desdeEquipo(
            $equipo,
            FormacionEquipo::desde($formacion),
            $nivelJugador,
        );

        return $this->creadorBatalla->crearYGuardar(
            datosJugador: $datosJugador,
            nombreJugador: $equipo->name,
            datosRival: $datosRival,
            nombreRival: $combateRival->entrenador,
            prefijoId: 'faccion',
            meta: [
                'tipo' => 'habitat_temporal',
                'faccion_slug' => $faccionSlug,
                'oleada' => $oleada,
                'combate' => $combate,
                'es_jefe' => $combateRival->esJefe,
                'nivel_rival' => $nivelRival,
                'user_id' => $userId,
                'team_id' => $teamId,
            ],
        );
    }
}
