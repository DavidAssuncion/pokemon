<?php

declare(strict_types=1);

namespace Src\Gimnasios\App;

use Src\Gimnasios\Domain\CatalogoGimnasios;
use Src\Gimnasios\Domain\Exceptions\GimnasioNoExiste;
use Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface;

/**
 * Devuelve el detalle de un gimnasio: información general, estado (bloqueado/
 * disponible/completado), etapa actual y nombres de las etapas (sin revelar
 * el equipo rival ni el nivel rival).
 */
final class ObtenerGimnasioDetalle
{
    public function __construct(
        private readonly CatalogoGimnasios $catalogo,
        private readonly GymProgressRepositoryInterface $repositorio,
    ) {
    }

    /**
     * @return array{
     *     slug: string,
     *     medalla: string,
     *     tipo: int,
     *     nivel_minimo: int,
     *     nivel_jugador: int,
     *     etapa_actual: int,
     *     estado: string,
     *     etapas: list<array{
     *         etapa: int,
     *         nombre: string
     *     }>
     * }
     *
     * @throws GimnasioNoExiste
     */
    public function obtener(string $slug, int $userId, int $nivelJugador): array
    {
        $gimnasio = $this->catalogo->porSlugOrFail($slug);

        $completado = $this->repositorio->esCompletado($userId, $slug);
        $etapaActual = $this->repositorio->obtenerProgreso($userId, $slug) ?? 1;
        $bloqueado = ! $completado && $nivelJugador < $gimnasio->nivelMinimo;

        $etapas = [];
        for ($etapa = 1; $etapa <= 4; $etapa++) {
            $etapas[] = [
                'etapa' => $etapa,
                'nombre' => $gimnasio->nombreEtapa($etapa),
            ];
        }

        return [
            'slug' => $gimnasio->slug,
            'medalla' => $gimnasio->medalla,
            'tipo' => $gimnasio->tipo->value,
            'nivel_minimo' => $gimnasio->nivelMinimo,
            'nivel_jugador' => $nivelJugador,
            'etapa_actual' => $completado ? 5 : $etapaActual,
            'estado' => $completado
                ? 'completado'
                : ($bloqueado ? 'bloqueado' : 'disponible'),
            'etapas' => $etapas,
        ];
    }
}
