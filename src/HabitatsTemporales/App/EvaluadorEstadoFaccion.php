<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\App;

use Src\HabitatsTemporales\Domain\ReglasHabitatTemporal;

/**
 * Calcula motivos de bloqueo por medallas para facciones y oleadas.
 */
final class EvaluadorEstadoFaccion
{
    public function motivoBloqueoOleada(int $oleada, int $medallas): ?string
    {
        $requeridas = ReglasHabitatTemporal::medallasRequeridasOleada($oleada);

        if ($medallas >= $requeridas) {
            return null;
        }

        return sprintf('Se desbloquea con %d medallas (tienes %d).', $requeridas, $medallas);
    }

    public function estadoFaccion(int $oleada, int $medallas): string
    {
        return $this->motivoBloqueoOleada($oleada, $medallas) === null
            ? 'disponible'
            : 'bloqueada';
    }
}
