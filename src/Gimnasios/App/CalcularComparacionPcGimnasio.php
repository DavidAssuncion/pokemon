<?php

declare(strict_types=1);

namespace Src\Gimnasios\App;

use App\Support\Pc\CalculadoraPcRival;
use App\Support\Pc\ConstruirLeyendaIvsEvs;
use App\Support\Pc\ObtenerPcEquipoJugador;
use App\Support\Pc\VistaPreviaRival;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Gimnasios\Domain\Repositories\GymCatalogoRepositoryInterface;
use Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface;
use Src\Shared\Domain\EscaladorNivelRival;
use Src\Shared\Domain\Pc\ComparacionPc;
use Src\Shared\Domain\Pc\ComparadorPc;
use Src\Shared\Domain\Pc\MetodoPcRival;
use Src\Shared\Domain\PoliticaIvsEvs;

/**
 * Compara el PC del equipo del jugador con el del roster de la etapa actual
 * del gimnasio.
 *
 * La etapa y el nivel rival se resuelven en servidor. Solo se expone el
 * agregado: nunca composición, especies ni nivel rival. La leyenda IV/EV se
 * construye siempre, también sin equipo seleccionado.
 */
final class CalcularComparacionPcGimnasio
{
    public function __construct(
        private readonly GymCatalogoRepositoryInterface $catalogo,
        private readonly GymProgressRepositoryInterface $repositorio,
        private readonly EscaladorNivelRival $escalador,
        private readonly GeneradorPokemonGimnasio $generador,
        private readonly CalculadoraPcRival $calculadoraPcRival,
        private readonly ObtenerPcEquipoJugador $obtenerPcEquipo,
        private readonly ComparadorPc $comparador,
        private readonly ConstruirLeyendaIvsEvs $constructorLeyenda,
    ) {
    }

    public function calcular(
        string $slug,
        ?int $teamId,
        int $userId,
        int $nivelJugador,
    ): VistaPreviaRival {
        $gimnasio = $this->catalogo->obtenerPorSlugOrFail($slug);

        if (
            $this->repositorio->esCompletado($userId, $slug)
            || $nivelJugador < $gimnasio->nivelMinimo
        ) {
            return new VistaPreviaRival(
                ComparacionPc::noDisponible(MetodoPcRival::CATALOGO_ETAPA),
                $this->constructorLeyenda->construir(null, 0, new DatosPokemonBatallaCollection()),
                null,
            );
        }

        $etapa = $this->repositorio->obtenerProgreso($userId, $slug) ?? 1;
        $nivelRival = $this->escalador->escalar($gimnasio->nivelMinimo, $nivelJugador);
        $indice = $this->catalogo->indiceDe($slug);
        $perfil = PoliticaIvsEvs::perfilGimnasio($indice, $etapa === 4);
        $equipoEtapa = $gimnasio->equipoEtapa($etapa);

        $datos = $equipoEtapa === null
            ? new DatosPokemonBatallaCollection()
            : $this->generador->generar(
                equipo: $equipoEtapa,
                nivelRival: $nivelRival,
                perfil: $perfil,
                indiceGimnasio: $indice,
                esLider: $etapa === 4,
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
}
