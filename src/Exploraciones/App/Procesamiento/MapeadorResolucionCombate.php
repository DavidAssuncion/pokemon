<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Procesamiento;

use Src\Exploraciones\Domain\ValueObjects\EstadoExplorador;
use Src\Exploraciones\Domain\ValueObjects\ResultadoBatallaExploracion;

/**
 * Traduce el resultado de un combate al contrato de resolución de eventos.
 *
 * Documenta el estado del explorador DESPUÉS del combate (ya con barreras
 * regeneradas al 100 % en victoria no-emboscada). Mantiene exactamente las
 * claves y el orden del shape persistido en exploracion->eventos.
 */
final class MapeadorResolucionCombate
{
    /**
     * @return array<string, mixed>
     */
    public function mapear(
        ResultadoBatallaExploracion $resultado,
        string $resolucionVictoria,
        EstadoExplorador $estadoExplorador,
    ): array {
        $resolucion = [
            'resolucion' => $resultado->victoria ? $resolucionVictoria : 'derrota',
            'victoria' => $resultado->victoria,
            'hp_final' => $estadoExplorador->hp,
            'barrera_fisica_final' => $estadoExplorador->barreraFisica,
            'barrera_especial_final' => $estadoExplorador->barreraEspecial,
            'barrera_fisica_max' => $estadoExplorador->barreraFisicaMax,
            'barrera_especial_max' => $estadoExplorador->barreraEspecialMax,
            'log' => $resultado->log->entries()->toList(),
            'duration_loss' => 0,
        ];

        $pv = [
            'hp_inicial' => $resultado->hpInicial,
            'hp_max' => $resultado->hpMax,
            'rival_hp_inicial' => $resultado->rivalHpInicial,
            'rival_hp_final' => $resultado->rivalHpFinal,
            'rival_hp_max' => $resultado->rivalHpMax,
        ];

        foreach ($pv as $clave => $valor) {
            if ($valor !== null) {
                $resolucion[$clave] = $valor;
            }
        }

        return $resolucion;
    }
}
