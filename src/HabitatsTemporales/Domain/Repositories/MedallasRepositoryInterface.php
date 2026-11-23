<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\Repositories;

/**
 * Lectura del número de medallas de gimnasio completadas por un jugador.
 */
interface MedallasRepositoryInterface
{
    public function contarCompletadas(int $userId): int;
}
