<?php

declare(strict_types=1);

namespace Src\CombateRuta\App;

use App\Models\Team;
use App\Support\CreadorBatallaSesion;
use Src\CombateEntrenadores\App\ConstruirEquipoJugador;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * Crea una batalla de ruta 5v5 (pokémon salvajes del hábitat) y la guarda en
 * sesión para el componente Livewire de combate.
 *
 * - Valida que el equipo del jugador tenga exactamente 5 miembros.
 * - Sin límite diario ni log: la ruta se puede repetir siempre que se quiera.
 * - El jugador es team1; "Pokémon salvajes" generados del pool es team2.
 */
final class IniciarCombateRuta
{
    public function __construct(
        private readonly GeneradorEquipoRuta $generadorEquipo,
        private readonly ConstruirEquipoJugador $construirEquipoJugador,
        private readonly CreadorBatallaSesion $creadorBatalla,
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
    ): string {
        $equipo = Team::with('members.reclutado.pokemon.stats', 'members.reclutado.pokemon.types')
            ->findOrFail($teamId);

        if ($equipo->members->count() !== 5) {
            throw new ViolacionReglaNegocio('El equipo de ruta debe tener exactamente 5 miembros.');
        }

        $datosJugador = $this->construirEquipoJugador->desdeEquipo($equipo, $formacion, $nivelJugador);

        $datosRival = $this->generadorEquipo->generar($habitatId, $nivel, $nivelJugador);
        if ($datosRival === []) {
            throw new ViolacionReglaNegocio('No hay Pokémon salvajes disponibles en esta ruta para tu nivel.');
        }

        return $this->creadorBatalla->crearYGuardar(
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
    }
}
