<?php

declare(strict_types=1);

namespace Src\Gimnasios\App;

use Src\Gimnasios\Domain\Collections\GimnasioResumenCollection;
use Src\Gimnasios\Domain\DataTransferObjects\GimnasioResumen;
use Src\Gimnasios\Domain\Repositories\GymCatalogoRepositoryInterface;
use Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface;

/**
 * Devuelve el listado de todos los gimnasios con el progreso del usuario,
 * estado (disponible/bloqueado/completado) y nivel mínimo requerido.
 */
final class ObtenerGimnasios
{
    public function __construct(
        private readonly GymCatalogoRepositoryInterface $catalogo,
        private readonly GymProgressRepositoryInterface $repositorio,
    ) {
    }

    public function obtener(int $userId, int $nivelJugador): GimnasioResumenCollection
    {
        $resultado = new GimnasioResumenCollection();

        foreach ($this->catalogo->obtenerTodos() as $gimnasio) {
            $completado = $this->repositorio->esCompletado($userId, $gimnasio->slug);
            $etapaActual = $this->repositorio->obtenerProgreso($userId, $gimnasio->slug) ?? 1;
            $bloqueado = ! $completado && $nivelJugador < $gimnasio->nivelMinimo;

            $resultado->add(new GimnasioResumen(
                slug: $gimnasio->slug,
                medalla: $gimnasio->medalla,
                tipo: $gimnasio->tipo,
                tipoNombre: $gimnasio->tipo->label(),
                tipoSlug: $gimnasio->tipo->slug(),
                tipoMedalla: $gimnasio->tipo->medalla(),
                tipoColor: $gimnasio->tipo->color(),
                nivelMinimo: $gimnasio->nivelMinimo,
                nivelJugador: $nivelJugador,
                etapaActual: $completado ? 5 : $etapaActual,
                estado: $completado
                    ? 'completado'
                    : ($bloqueado ? 'bloqueado' : 'disponible'),
            ));
        }

        return $resultado;
    }
}
