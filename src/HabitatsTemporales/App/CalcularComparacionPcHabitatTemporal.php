<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\App;

use App\Support\Pc\CalculadoraPcRival;
use App\Support\Pc\ConstruirLeyendaIvsEvs;
use App\Support\Pc\ObtenerPcEquipoJugador;
use App\Support\Pc\VistaPreviaRival;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Gimnasios\App\GeneradorPokemonGimnasio;
use Src\HabitatsTemporales\Domain\CatalogoFaccionesRivales;
use Src\HabitatsTemporales\Domain\DataTransferObjects\ProgresoHabitatTemporal;
use Src\HabitatsTemporales\Domain\ReglasHabitatTemporal;
use Src\HabitatsTemporales\Domain\Repositories\MedallasRepositoryInterface;
use Src\HabitatsTemporales\Domain\Repositories\ProgresoHabitatTemporalRepositoryInterface;
use Src\Shared\Domain\Pc\ComparacionPc;
use Src\Shared\Domain\Pc\ComparadorPc;
use Src\Shared\Domain\Pc\MetodoPcRival;

/**
 * Compara el PC del equipo del jugador con el del combate actual del hábitat
 * temporal. No expone composición, especies ni nivel del rival.
 */
final class CalcularComparacionPcHabitatTemporal
{
    public function __construct(
        private readonly CatalogoFaccionesRivales $catalogo,
        private readonly ProgresoHabitatTemporalRepositoryInterface $progresos,
        private readonly MedallasRepositoryInterface $medallas,
        private readonly EvaluadorEstadoFaccion $evaluador,
        private readonly GeneradorPokemonGimnasio $generador,
        private readonly CalculadoraPcRival $calculadoraPcRival,
        private readonly ObtenerPcEquipoJugador $obtenerPcEquipo,
        private readonly ComparadorPc $comparador,
        private readonly ConstruirLeyendaIvsEvs $constructorLeyenda,
    ) {
    }

    public function calcular(
        string $faccionSlug,
        ?int $teamId,
        int $userId,
        int $nivelJugador,
    ): VistaPreviaRival {
        $faccion = $this->catalogo->porSlug($faccionSlug);
        $medallas = $this->medallas->contarCompletadas($userId);
        $progreso = $this->progresos->obtener($userId, $faccionSlug)
            ?? ProgresoHabitatTemporal::inicial();

        if ($medallas < $faccion->medallasDesbloqueo || $progreso->completada) {
            return $this->noDisponible();
        }

        if ($this->evaluador->motivoBloqueoOleada($progreso->oleadaActual, $medallas) !== null) {
            return $this->noDisponible();
        }

        $combate = $faccion->oleada($progreso->oleadaActual)?->combate($progreso->combateActual);

        if ($combate === null) {
            return $this->noDisponible();
        }

        $nivelRival = ReglasHabitatTemporal::nivelRival($nivelJugador);
        $perfil = ReglasHabitatTemporal::perfilIvsEvs($progreso->oleadaActual, $combate->esJefe);
        $indice = ReglasHabitatTemporal::indiceDificultad($faccionSlug);

        $datos = $this->generador->generar(
            equipo: $combate->equipo,
            nivelRival: $nivelRival,
            perfil: $perfil,
            indiceGimnasio: $indice,
            esLider: $combate->esJefe,
            movimientosDesbloqueados: [],
        );

        $leyenda = $this->constructorLeyenda->construir($perfil, $nivelRival, $datos);
        $equipo = $this->obtenerPcEquipo->obtener($teamId, $userId, $nivelJugador);

        if (! $equipo->completo || $equipo->pc === null) {
            return new VistaPreviaRival(
                ComparacionPc::equipoIncompleto(
                    $equipo->miembros,
                    MetodoPcRival::CATALOGO_ETAPA,
                    false,
                ),
                $leyenda,
                null,
            );
        }

        $pcRival = $this->calculadoraPcRival->desdeDatosBatalla($datos, $nivelRival);

        if ($pcRival === null) {
            return new VistaPreviaRival(
                ComparacionPc::sinRival(
                    $equipo->pc,
                    $equipo->miembros,
                    MetodoPcRival::CATALOGO_ETAPA,
                    false,
                ),
                $leyenda,
                null,
            );
        }

        return new VistaPreviaRival(
            ComparacionPc::ok(
                miembros: $equipo->miembros,
                pcEquipo: $equipo->pc,
                pcRival: $pcRival,
                metodo: MetodoPcRival::CATALOGO_ETAPA,
                aproximado: false,
                ratio: $this->comparador->ratio($equipo->pc, $pcRival),
                veredicto: $this->comparador->comparar($equipo->pc, $pcRival),
            ),
            $leyenda,
            null,
        );
    }

    private function noDisponible(): VistaPreviaRival
    {
        return new VistaPreviaRival(
            ComparacionPc::noDisponible(MetodoPcRival::CATALOGO_ETAPA),
            $this->constructorLeyenda->construir(null, 0, new DatosPokemonBatallaCollection()),
            null,
        );
    }
}
