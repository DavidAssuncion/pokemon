<?php

declare(strict_types=1);

namespace Src\Gimnasios\App;

use Src\Gimnasios\Domain\CatalogoGimnasios;
use Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface;

/**
 * Devuelve el listado de todos los gimnasios con el progreso del usuario,
 * estado (disponible/bloqueado/completado) y nivel mínimo requerido.
 */
final class ObtenerGimnasios
{
    public function __construct(
        private readonly CatalogoGimnasios $catalogo,
        private readonly GymProgressRepositoryInterface $repositorio,
    ) {
    }

    /**
     * @return list<array{
     *     slug: string,
     *     medalla: string,
     *     tipo: int,
     *     nivel_minimo: int,
     *     nivel_jugador: int,
     *     etapa_actual: int,
     *     estado: string
     * }>
     */
    public function obtener(int $userId, int $nivelJugador): array
    {
        $resultado = [];

        foreach ($this->catalogo->todos() as $gimnasio) {
            $completado = $this->repositorio->esCompletado($userId, $gimnasio->slug);
            $etapaActual = $this->repositorio->obtenerProgreso($userId, $gimnasio->slug) ?? 1;
            $bloqueado = ! $completado && $nivelJugador < $gimnasio->nivelMinimo;

            $resultado[] = [
                'slug' => $gimnasio->slug,
                'medalla' => $gimnasio->medalla,
                'tipo' => $gimnasio->tipo->value,
                'nivel_minimo' => $gimnasio->nivelMinimo,
                'nivel_jugador' => $nivelJugador,
                'etapa_actual' => $completado ? 5 : $etapaActual,
                'estado' => $completado
                    ? 'completado'
                    : ($bloqueado ? 'bloqueado' : 'disponible'),
            ];
        }

        return $resultado;
    }
}
