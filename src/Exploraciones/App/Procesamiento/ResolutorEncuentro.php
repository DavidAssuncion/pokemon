<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Procesamiento;

use App\Models\Pokemon;

/**
 * Resuelve un evento de encuentro con combate real 1v1.
 *
 * Conserva el orden original: descanso previo si el explorador está por debajo
 * del 50 %, combate y mapeo al contrato de resolución. El `duration_loss` del
 * descanso previo se mantiene en 0 por compatibilidad con el contrato actual.
 */
final class ResolutorEncuentro
{
    public function __construct(
        private readonly MapeadorResolucionCombate $mapeador,
        private readonly EjecutorCombateExplorador $ejecutor,
        private readonly AplicadorDescansoExplorador $descanso,
    ) {
    }

    /**
     * @param  array<string, mixed>  $evento
     * @return array<string, mixed>
     */
    public function resolver(array $evento, ContextoTickExploracion $contexto): array
    {
        $pokemonId = (int) ($evento['pokemon_id'] ?? 0);
        $salvaje = Pokemon::find($pokemonId);

        if ($salvaje === null) {
            return array_merge($evento, ['resolucion' => 'derrota', 'duration_loss' => 0, 'derrota' => true]);
        }

        $perdidoAntes = $this->descanso->aplicar($contexto);
        $resultado = $this->ejecutor->combatir($contexto, $salvaje, false);

        return array_merge(
            $evento,
            $this->mapeador->mapear($resultado, 'victoria', $contexto->estado) + ['duration_loss' => $perdidoAntes],
        );
    }
}
