<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\Repositories;

use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;
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
    /**
     * @return list<Gimnasio>
     */
    public function obtenerTodos(): array;

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
        /** @var array<int, EquipoEtapaGimnasio> */
        array $equipos = [],
    ): Gimnasio;

    /**
     * Actualiza los datos fijos de un gimnasio (no las etapas). Exige slug existente.
     */
    public function actualizarDatos(string $slug, string $medalla, TipoPokemon $tipo, int $nivelMinimo): Gimnasio;

    /**
     * (Re)escribe las etapas (1-4) de un gimnasio.
     *
     * @param  array<int, EquipoEtapaGimnasio>  $equipos
     */
    public function actualizarEtapas(string $slug, array $equipos): void;
}
