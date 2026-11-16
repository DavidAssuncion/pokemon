<?php

declare(strict_types=1);

namespace Src\CombateRuta\App;

use App\Models\Team;
use App\Support\CargarMovimientosDesbloqueados;
use App\Support\CombateRuta\AlmacenRivalRutaFijado;
use App\Support\CreadorBatallaSesion;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\CombateEntrenadores\App\ConstruirEquipoJugador;
use Src\CombateEntrenadores\Domain\ValueObjects\FormacionEquipo;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * Crea una batalla de ruta 3v3 (pokémon salvajes del hábitat) y la guarda en
 * sesión para el componente Livewire de combate.
 *
 * - Valida que el equipo del jugador aporte exactamente 3 miembros
 *   (guard centralizado en ConstruirEquipoJugador::desdeEquipo).
 * - Sin límite diario ni log: la ruta se puede repetir siempre que se quiera.
 * - El jugador es team1; "Pokémon salvajes" generados del pool es team2.
 * - Si llega un `preview_id` válido, reutiliza el rival fijado en la preview y
 *   limpia el snapshot tras crear la batalla. Si no, genera uno nuevo.
 */
final class IniciarCombateRuta
{
    public function __construct(
        private readonly GeneradorEquipoRuta $generadorEquipo,
        private readonly ConstruirEquipoJugador $construirEquipoJugador,
        private readonly CreadorBatallaSesion $creadorBatalla,
        private readonly CargarMovimientosDesbloqueados $cargarMovimientos,
        private readonly AlmacenRivalRutaFijado $almacen,
    ) {
    }

    /**
     * @param  array<int, string>  $formacion  posición por slot del equipo jugador
     */
    public function iniciar(
        int $habitatId,
        int $nivel,
        int $teamId,
        int $userId,
        int $nivelJugador,
        array $formacion = [],
        ?string $previewId = null,
    ): string {
        $equipo = Team::with('members.reclutado.pokemon.stats', 'members.reclutado.pokemon.types')
            ->findOrFail($teamId);

        $datosJugador = $this->construirEquipoJugador->desdeEquipo($equipo, FormacionEquipo::desde($formacion), $nivelJugador);

        $datosRival = $this->resolverRival($habitatId, $nivel, $userId, $nivelJugador, $previewId);

        if ($datosRival->isEmpty()) {
            throw new ViolacionReglaNegocio('No hay Pokémon salvajes disponibles en esta ruta para tu nivel.');
        }

        $battleId = $this->creadorBatalla->crearYGuardar(
            datosJugador: $datosJugador,
            nombreJugador: $equipo->name,
            datosRival: $datosRival,
            nombreRival: 'Pokémon salvajes',
            prefijoId: 'ruta',
            meta: [
                'tipo' => 'ruta',
                'habitat_id' => $habitatId,
                'nivel' => $nivel,
                'user_id' => $userId,
                'team_id' => $teamId,
            ],
        );

        if ($previewId !== null) {
            $this->almacen->limpiar($userId, $previewId);
        }

        return $battleId;
    }

    private function resolverRival(
        int $habitatId,
        int $nivel,
        int $userId,
        int $nivelJugador,
        ?string $previewId,
    ): DatosPokemonBatallaCollection {
        if ($previewId !== null) {
            $fijado = $this->almacen->obtener($userId, $previewId, $habitatId, $nivel);

            if ($fijado !== null) {
                return $fijado->nivelJugador === $nivelJugador
                    ? $fijado->equipo
                    : $fijado->equipo->conNivel($nivelJugador);
            }
        }

        return $this->generadorEquipo->generar(
            $habitatId,
            $nivel,
            $nivelJugador,
            movimientosDesbloqueados: $this->cargarMovimientos->para($userId),
        );
    }
}
