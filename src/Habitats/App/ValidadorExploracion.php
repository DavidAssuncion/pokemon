<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use App\Models\ExploracionActiva;

class ValidadorExploracion
{
    /**
     * Check if a team can start a new exploration (not already in one).
     */
    public function equipoDisponible(int $teamId): bool
    {
        return !ExploracionActiva::where('equipo_id', $teamId)
            ->whereNull('regreso')
            ->exists();
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
            ->with('team.members.reclutado.pokemon')
            ->get()
            ->toArray();
    }
}
