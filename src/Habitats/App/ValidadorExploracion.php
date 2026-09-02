<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use App\Models\ExploracionActiva;
use App\Models\Reclutado;

class ValidadorExploracion
{
    /**
     * Check if a team can start a new exploration (not already in one).
     * Las exploraciones son por reclutado: el equipo está "en exploración"
     * si alguno de sus miembros tiene una exploración activa.
     */
    public function equipoDisponible(int $teamId): bool
    {
        $miembroIds = \App\Models\TeamMember::where('team_id', $teamId)->pluck('pokemon_id');

        return ! ExploracionActiva::whereNull('regreso')
            ->whereIn('reclutado_id', $miembroIds)
            ->exists();
    }

    /**
     * Check if a reclutado can start a new exploration (not already in one).
     */
    public function reclutadoDisponible(int $reclutadoId): bool
    {
        return !ExploracionActiva::where('reclutado_id', $reclutadoId)
            ->whereNull('regreso')
            ->exists();
    }

    /**
     * Check if the team of the reclutado (if any) is NOT in active exploration.
     */
    public function equipoDelReclutadoDisponible(int $reclutadoId): bool
    {
        $teamMember = Reclutado::query()->find($reclutadoId)?->teamMember;

        if ($teamMember === null || $teamMember->team === null) {
            return true; // Sin equipo → sin restricción.
        }

        return ! $teamMember->team->isExploring();
    }

    /**
     * Regla de negocio: un jugador puede explorar una zona si su nivel es
     * mayor o igual al mínimo requerido por el hábitat para ese nivel de
     * exploración. null = sin restricción.
     */
    public function cumpleNivelMinimo(int $nivelJugador, ?int $nivelMinimo): bool
    {
        return $nivelMinimo === null || $nivelJugador >= $nivelMinimo;
    }

    /**
     * Check if a team can be used for combat (not in active exploration).
     */
    public function equipoDisponibleParaCombate(int $teamId): bool
    {
        return $this->equipoDisponible($teamId);
    }

    /**
     * Check if a habitat has any active explorations (for construction blocking).
     */
    public function habitatTieneExploracionesActivas(int $habitatId): bool
    {
        return ExploracionActiva::where('habitat_id', $habitatId)
            ->whereNull('regreso')
            ->exists();
    }

    /**
     * Get all active explorations for a habitat.
     *
     * @return array<int, \App\Models\ExploracionActiva>
     */
    public function exploracionesActivas(int $habitatId): array
    {
        return ExploracionActiva::where('habitat_id', $habitatId)
            ->whereNull('regreso')
            ->with('reclutado.pokemon')
            ->get()
            ->toArray();
    }
}
