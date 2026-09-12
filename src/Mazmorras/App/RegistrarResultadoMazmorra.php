<?php

declare(strict_types=1);

namespace Src\Mazmorras\App;

use Src\Mazmorras\Domain\DataTransferObjects\ResultadoMazmorra;
use Src\Mazmorras\Domain\Repositories\DungeonProgresoRepositoryInterface;

/**
 * Persiste el resultado de un combate de mazmorra:
 * - victoria → avanza el piso actual (completa la mazmorra en el último).
 * - derrota → registra el log (cooldown de 1h para reintentar el piso).
 * Solo aplica si el userId coincide con el autenticado (anti-IDOR).
 */
final class RegistrarResultadoMazmorra
{
    public function __construct(
        private readonly DungeonProgresoRepositoryInterface $repositorio,
    ) {
    }

    public function registrar(
        int $habitatId,
        int $piso,
        int $totalPisos,
        int $userId,
        bool $won,
        int $authUserId,
    ): ResultadoMazmorra {
        if ($userId !== $authUserId) {
            return new ResultadoMazmorra(avance: false, completado: false);
        }

        if (! $won) {
            $this->repositorio->registrarDerrota($userId, $habitatId, $piso);

            return new ResultadoMazmorra(avance: false, completado: false);
        }

        $this->repositorio->registrarVictoria($userId, $habitatId, $piso, $totalPisos);

        return new ResultadoMazmorra(avance: true, completado: $piso + 1 > $totalPisos);
    }
}
