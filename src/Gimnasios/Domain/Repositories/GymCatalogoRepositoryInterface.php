<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\Repositories;

use Src\Gimnasios\Domain\Collections\EquipoEtapaGimnasioCollection;
use Src\Gimnasios\Domain\Collections\GimnasioCollection;
use Src\Gimnasios\Domain\Gimnasio;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Catálogo de gimnasios persistido en BD (tablas gyms + gym_stages).
 *
 * Sustituye a CatalogoGimnasios como fuente para los casos de uso. El CRUD
 * NO elimina gyms: no existe borrado ni desactivación.
 */
interface GymCatalogoRepositoryInterface
{
    public function obtenerTodos(): GimnasioCollection;

    /**
     * NO devuelve null: si el slug no existe lanza GimnasioNoExiste.
     */
    public function obtenerPorSlugOrFail(string $slug): Gimnasio;

    /**
     * Crea un gimnasio sin etapas (o con las etapas indicadas). Devuelve la entidad.
     */
    public function insertar(
        string $slug,
        string $medalla,
        TipoPokemon $tipo,
        int $nivelMinimo,
        EquipoEtapaGimnasioCollection $equipos = new EquipoEtapaGimnasioCollection(),
    ): Gimnasio;

    /**
     * Actualiza los datos fijos de un gimnasio (no las etapas). Exige slug existente.
     */
    public function actualizarDatos(string $slug, string $medalla, TipoPokemon $tipo, int $nivelMinimo): Gimnasio;

    /**
     * (Re)escribe las etapas (1-4) de un gimnasio.
     */
    public function actualizarEtapas(string $slug, EquipoEtapaGimnasioCollection $equipos): void;
}
