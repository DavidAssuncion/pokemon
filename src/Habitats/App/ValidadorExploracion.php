<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use App\Models\ExploracionActiva;
use Src\Habitats\App\Collections\ExploracionActivaCollection;

class ValidadorExploracion
{
    /**
     * Indica si el reclutado tiene una exploración activa (sin regreso).
     *
     * La exploración es individual: solo el reclutado que explora queda
     * bloqueado, no el resto de su equipo.
     */
    public function reclutadoEnExploracion(int $reclutadoId): bool
    {
        return ExploracionActiva::where('reclutado_id', $reclutadoId)
            ->whereNull('regreso')
            ->exists();
    }

    /**
     * Check if a reclutado can start a new exploration (not already in one).
     */
    public function reclutadoDisponible(int $reclutadoId): bool
    {
        return ! $this->reclutadoEnExploracion($reclutadoId);
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
     */
    public function exploracionesActivas(int $habitatId): ExploracionActivaCollection
    {
        return new ExploracionActivaCollection(
            ExploracionActiva::where('habitat_id', $habitatId)
                ->whereNull('regreso')
                ->with('reclutado.pokemon')
                ->get()
                ->all()
        );
    }
}
