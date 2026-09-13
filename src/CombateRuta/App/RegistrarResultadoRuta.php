<?php

declare(strict_types=1);

namespace Src\CombateRuta\App;

use Src\CombateRuta\Domain\DataTransferObjects\ResultadoRuta;

/**
 * Procesa el resultado de un combate de ruta: la derrota no otorga nada y la
 * victoria devuelve el modal con recompensas y capturas.
 *
 * Sin bloqueo ni log diario: el combate de ruta siempre se puede reintentar.
 */
final class RegistrarResultadoRuta
{
    public function __construct(
        private readonly OtorgarRecompensasRuta $otorgarRecompensas,
    ) {
    }

    /**
     * @param  list<int>  $speciesIdsRival
     * @param  callable(): float|null  $aleatorio  fuerza el roll de captura en tests
     */
    public function registrar(
        int $userId,
        int $teamId,
        array $speciesIdsRival,
        bool $won,
        ?callable $aleatorio = null,
    ): ?ResultadoRuta {
        if (! $won) {
            return null;
        }

        return $this->otorgarRecompensas->otorgar(
            userId: $userId,
            teamId: $teamId,
            speciesIdsRival: $speciesIdsRival,
            aleatorio: $aleatorio,
        );
    }
}
