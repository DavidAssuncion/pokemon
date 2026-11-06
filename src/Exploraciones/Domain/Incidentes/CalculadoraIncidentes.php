<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Incidentes;

/**
 * Cuenta encuentros, victorias, huidas, emboscadas y contratiempos de una
 * bitácora de exploración ya normalizada.
 */
final class CalculadoraIncidentes
{
    /**
     * @param  list<array<string, mixed>>  $bitacora
     */
    public function calcular(array $bitacora): IncidentesExploracion
    {
        $encuentros = 0;
        $victorias = 0;
        $huidas = 0;
        $emboscadas = 0;
        $contratiempos = 0;

        foreach ($bitacora as $evento) {
            $tipo = $evento['tipo'] ?? '';
            $resolucion = $evento['resolucion'] ?? null;

            if ($tipo === 'emboscada') {
                $emboscadas++;
            } elseif ($tipo === 'contratiempo') {
                $contratiempos++;
            } elseif ($tipo === 'encuentro' || $tipo === 'pokemon') {
                $encuentros++;
                if ($resolucion === null || $resolucion === 'victoria') {
                    $victorias++;
                }
            } elseif ($resolucion === 'huida') {
                $huidas++;
            }
        }

        return new IncidentesExploracion(
            encuentros: $encuentros,
            victorias: $victorias,
            huidas: $huidas,
            emboscadas: $emboscadas,
            contratiempos: $contratiempos,
        );
    }
}
