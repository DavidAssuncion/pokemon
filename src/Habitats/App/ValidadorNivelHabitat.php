<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use Src\Habitats\Domain\Repositories\HabitatLecturaRepositoryInterface;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * Regla de acceso a un nivel de hábitat: solo se puede entrar si al menos un
 * spawn de ese nivel tiene `min_level <= nivel del jugador`.
 *
 * La misma regla se usa en exploración, ruta y entrenadores para que el
 * bloqueo visual y el backend no diverjan.
 */
final class ValidadorNivelHabitat
{
    public function __construct(private readonly HabitatLecturaRepositoryInterface $repository)
    {
    }

    public function nivelMinimo(int $habitatId, int $nivel): ?int
    {
        return $this->repository->minLevelForHabitatLevel($habitatId, $nivel);
    }

    public function disponible(int $habitatId, int $nivel, int $nivelJugador): bool
    {
        return $this->repository->levelAvailableForPlayer($habitatId, $nivel, $nivelJugador);
    }

    /**
     * @throws ViolacionReglaNegocio
     */
    public function asegurar(int $habitatId, int $nivel, int $nivelJugador): void
    {
        if ($this->disponible($habitatId, $nivel, $nivelJugador)) {
            return;
        }

        $minimo = $this->nivelMinimo($habitatId, $nivel);

        if ($minimo === null) {
            throw new ViolacionReglaNegocio('Ese nivel del hábitat no tiene Pokémon disponibles.');
        }

        throw new ViolacionReglaNegocio("Requiere nivel Nv {$minimo} para acceder a este nivel del hábitat.");
    }
}
