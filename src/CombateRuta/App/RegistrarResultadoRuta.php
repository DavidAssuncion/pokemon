<?php

declare(strict_types=1);

namespace Src\CombateRuta\App;

use Src\CombateRuta\Domain\DataTransferObjects\ResultadoRuta;
use Src\Shared\Domain\MultiplicadoresDificultad;

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
     * @param  array<int, bool>  $shinySlots  flags shiny alineados con $speciesIdsRival
     * @param  MultiplicadoresDificultad|null  $dificultad  multiplicadores por perfil del rival
     * @param  int|null  $nivelHabitat  nivel 1-3 del hábitat; `null` equivale a 1
     */
    public function registrar(
        int $userId,
        int $teamId,
        array $speciesIdsRival,
        bool $won,
        ?callable $aleatorio = null,
        array $shinySlots = [],
        ?MultiplicadoresDificultad $dificultad = null,
        ?int $nivelHabitat = null,
    ): ?ResultadoRuta {
        if (! $won) {
            return null;
        }

        return $this->otorgarRecompensas->otorgar(
            userId: $userId,
            teamId: $teamId,
            speciesIdsRival: $speciesIdsRival,
            aleatorio: $aleatorio,
            shinySlots: $shinySlots,
            dificultad: $dificultad,
            nivelHabitat: $nivelHabitat,
        );
    }
}
