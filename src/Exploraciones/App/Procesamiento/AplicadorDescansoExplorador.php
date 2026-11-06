<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Procesamiento;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Src\Exploraciones\Domain\Tick\DescansoAplicado;
use Src\Exploraciones\Domain\Tick\GestorDescansoExplorador;
use Src\Shared\Domain\Ports\Reloj;

/**
 * Aplica el descanso al explorador y anota el evento de bitácora.
 *
 * El cálculo es puro (GestorDescansoExplorador); esta clase solo aporta el
 * timestamp del reloj y el append al contrato persistido.
 */
final class AplicadorDescansoExplorador
{
    public function __construct(
        private readonly Reloj $reloj,
    ) {
    }

    /**
     * Devuelve los minutos de descanso aplicados (0 si no procede).
     */
    public function aplicar(ContextoTickExploracion $contexto): int
    {
        $descanso = GestorDescansoExplorador::calcular(
            $contexto->estado,
            $contexto->capacidades->multiplicadorRecuperacion($contexto->dificultad),
        );

        if ($descanso === null) {
            return 0;
        }

        $contexto->estado = $descanso->estado;
        $this->anotar($contexto->eventos, $descanso);

        return $descanso->duracionMinutos;
    }

    /**
     * @param  Collection<string, mixed>  $eventos
     */
    private function anotar(Collection $eventos, DescansoAplicado $descanso): void
    {
        $bitacora = $eventos->get('bitacora', []);
        $eventos->put('bitacora', [...$bitacora, [
            'tipo' => 'descanso',
            'timestamp' => Carbon::instance($this->reloj->ahora())->toIso8601String(),
            'duracion_minutos' => $descanso->duracionMinutos,
            'hp_recuperado' => $descanso->hpRecuperado,
        ]]);
    }
}
