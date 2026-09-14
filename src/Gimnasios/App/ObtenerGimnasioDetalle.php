<?php

declare(strict_types=1);

namespace Src\Gimnasios\App;

use Src\Gimnasios\Domain\Collections\EtapaGimnasioCollection;
use Src\Gimnasios\Domain\DataTransferObjects\DetalleGimnasio;
use Src\Gimnasios\Domain\DataTransferObjects\EtapaGimnasio;
use Src\Gimnasios\Domain\Exceptions\GimnasioNoExiste;
use Src\Gimnasios\Domain\Repositories\GymCatalogoRepositoryInterface;
use Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface;

/**
 * Devuelve el detalle de un gimnasio: información general, estado (bloqueado/
 * disponible/completado), etapa actual y nombres de las etapas (sin revelar
 * el equipo rival ni el nivel rival).
 */
final class ObtenerGimnasioDetalle
{
    public function __construct(
        private readonly GymCatalogoRepositoryInterface $catalogo,
        private readonly GymProgressRepositoryInterface $repositorio,
    ) {
    }

    /**
     * @throws GimnasioNoExiste
     */
    public function obtener(string $slug, int $userId, int $nivelJugador): DetalleGimnasio
    {
        $gimnasio = $this->catalogo->obtenerPorSlugOrFail($slug);

        $completado = $this->repositorio->esCompletado($userId, $slug);
        $etapaActual = $this->repositorio->obtenerProgreso($userId, $slug) ?? 1;
        $bloqueado = ! $completado && $nivelJugador < $gimnasio->nivelMinimo;

        $etapas = new EtapaGimnasioCollection();
        for ($etapa = 1; $etapa <= 4; $etapa++) {
            $etapas->add(new EtapaGimnasio(
                etapa: $etapa,
                nombre: $gimnasio->nombreEtapa($etapa),
            ));
        }

        return new DetalleGimnasio(
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
            etapas: $etapas,
        );
    }
}
