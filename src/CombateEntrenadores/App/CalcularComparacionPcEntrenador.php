<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Support\Pc\CalculadoraPcRival;
use App\Support\Pc\ConstruirLeyendaIvsEvs;
use App\Support\Pc\ObtenerPcEquipoJugador;
use App\Support\Pc\VistaPreviaRival;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\CombateEntrenadores\Domain\Repositories\EntrenadorLogRepositoryInterface;
use Src\Shared\Domain\Pc\ComparacionPc;
use Src\Shared\Domain\Pc\ComparadorPc;
use Src\Shared\Domain\Pc\MetodoPcRival;
use Src\Shared\Domain\PoliticaIvsEvs;

/**
 * Compara el PC del equipo del jugador con el del entrenador de hábitat.
 *
 * Reutiliza el generador real con la fecha del servidor: el rival es
 * determinista y el valor coincide con el que se combatirá. No se generan
 * movimientos porque no alteran stats ni selección de especies. La leyenda
 * IV/EV se construye siempre, aunque el equipo esté incompleto.
 */
final class CalcularComparacionPcEntrenador
{
    public function __construct(
        private readonly GeneradorEquipoEntrenador $generadorEquipo,
        private readonly EntrenadorLogRepositoryInterface $logRepository,
        private readonly CalculadoraPcRival $calculadoraPcRival,
        private readonly ObtenerPcEquipoJugador $obtenerPcEquipo,
        private readonly ComparadorPc $comparador,
        private readonly ConstruirLeyendaIvsEvs $constructorLeyenda,
    ) {
    }

    public function calcular(
        int $habitatId,
        int $nivel,
        int $trainerIndex,
        int $teamId,
        int $userId,
        int $nivelJugador,
        string $fecha,
    ): VistaPreviaRival {
        if ($this->logRepository->haGanadoHoy($userId, $habitatId, $nivel, $trainerIndex, $fecha)) {
            return new VistaPreviaRival(
                ComparacionPc::noDisponible(MetodoPcRival::GENERADOR_DETERMINISTA),
                $this->constructorLeyenda->construir(null, 0, new DatosPokemonBatallaCollection()),
                null,
            );
        }

        $perfil = PoliticaIvsEvs::perfilEntrenador($nivel);

        $datos = $this->generadorEquipo->generar(
            habitatId: $habitatId,
            nivel: $nivel,
            entrenadorIndex: $trainerIndex,
            fecha: $fecha,
            nivelRival: $nivelJugador,
            perfil: $perfil,
            movimientosDesbloqueados: [],
        );

        $leyenda = $this->constructorLeyenda->construir($perfil, $nivelJugador, $datos);

        $equipo = $this->obtenerPcEquipo->obtener($teamId, $userId, $nivelJugador);

        if (! $equipo->completo || $equipo->pc === null) {
            return new VistaPreviaRival(
                ComparacionPc::equipoIncompleto(
                    $equipo->miembros,
                    MetodoPcRival::GENERADOR_DETERMINISTA,
                    false,
                ),
                $leyenda,
                null,
            );
        }

        $pcRival = $this->calculadoraPcRival->desdeDatosBatalla($datos, $nivelJugador);

        if ($pcRival === null) {
            return new VistaPreviaRival(
                ComparacionPc::sinRival(
                    $equipo->pc,
                    $equipo->miembros,
                    MetodoPcRival::GENERADOR_DETERMINISTA,
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
                metodo: MetodoPcRival::GENERADOR_DETERMINISTA,
                aproximado: false,
                ratio: $this->comparador->ratio($equipo->pc, $pcRival),
                veredicto: $this->comparador->comparar($equipo->pc, $pcRival),
            ),
            $leyenda,
            null,
        );
    }
}
