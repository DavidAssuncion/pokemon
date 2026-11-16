<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\Services;

use LogicException;
use Src\Avistamientos\Domain\ValueObjects\FamiliaCandidata;
use Src\Avistamientos\Domain\ValueObjects\FamiliasCandidatasCollection;
use Src\Avistamientos\Domain\ValueObjects\HabitatCandidato;
use Src\Avistamientos\Domain\ValueObjects\HabitatsCandidatosCollection;
use Src\Avistamientos\Domain\ValueObjects\VentanaAvistamiento;

/**
 * Elige de forma determinista el hábitat y la familia de una ventana.
 *
 * Las listas llegan ordenadas por id y la sal está versionada: dos jugadores
 * obtienen la misma selección y un cambio de algoritmo no altera una ventana
 * en curso.
 */
final class SelectorAvistamiento
{
    private const string SAL_HABITAT = 'avistamientos:v1:habitat:%d';

    private const string SAL_FAMILIA = 'avistamientos:v1:familia:%d:%d';

    public function habitat(
        HabitatsCandidatosCollection $candidatos,
        VentanaAvistamiento $ventana,
    ): HabitatCandidato {
        if ($candidatos->isEmpty()) {
            throw new LogicException('No hay hábitats candidatos para el avistamiento.');
        }

        $indice = $this->indice(
            sprintf(self::SAL_HABITAT, $ventana->indice),
            $candidatos->count(),
        );

        return $candidatos->item($indice)
            ?? throw new LogicException('El hábitat elegido no existe en la colección de candidatos.');
    }

    public function familia(
        FamiliasCandidatasCollection $candidatos,
        VentanaAvistamiento $ventana,
        int $habitatId,
    ): FamiliaCandidata {
        if ($candidatos->isEmpty()) {
            throw new LogicException('No hay familias candidatas para el hábitat del avistamiento.');
        }

        $indice = $this->indice(
            sprintf(self::SAL_FAMILIA, $ventana->indice, $habitatId),
            $candidatos->count(),
        );

        return $candidatos->item($indice)
            ?? throw new LogicException('La familia elegida no existe en la colección de candidatas.');
    }

    private function indice(string $sal, int $total): int
    {
        return ((int) sprintf('%u', crc32($sal))) % $total;
    }
}
