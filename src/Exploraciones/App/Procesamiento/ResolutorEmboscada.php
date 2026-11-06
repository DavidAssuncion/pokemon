<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Procesamiento;

use App\Models\Pokemon;
use Closure;
use Src\Exploraciones\Domain\Tick\CalculadoraEvasionEmboscada;
use Src\Exploraciones\Domain\ValueObjects\ResultadoBatallaExploracion;

/**
 * Resuelve una emboscada: sub-combates secuenciales sin regenerar barreras.
 *
 * La detección puede evitar la emboscada sin combatir (MAESTRO siempre;
 * COMPETENTE/EXPERTO por tirada). Una emboscada evitada genera UN evento extra
 * en el gestor del tick. Conserva el orden de tiradas y de sub-combates.
 */
final class ResolutorEmboscada
{
    /**
     * @param  Closure():float  $aleatorio
     */
    public function __construct(
        private readonly MapeadorResolucionCombate $mapeador,
        private readonly EjecutorCombateExplorador $ejecutor,
        private readonly AplicadorDescansoExplorador $descanso,
        private readonly CalculadoraEvasionEmboscada $evasion,
        private readonly Closure $aleatorio,
    ) {
    }

    /**
     * @param  array<string, mixed>  $evento
     * @return array<string, mixed>
     */
    public function resolver(array $evento, ContextoTickExploracion $contexto): array
    {
        $ids = array_values(array_map('intval', (array) ($evento['pokemon_ids'] ?? [])));

        if ($ids === []) {
            return array_merge($evento, ['resolucion' => 'derrota', 'duration_loss' => 0, 'derrota' => true]);
        }

        if ($this->evasion->evitaAutomaticamente($contexto->capacidades, $contexto->dificultad)) {
            return $this->evitada($evento);
        }

        if (
            $contexto->capacidades->permitirEmboscadas($contexto->dificultad)
            && $this->evasion->evitaConTirada(($this->aleatorio)())
        ) {
            return $this->evitada($evento);
        }

        return $this->combatir($evento, $ids, $contexto);
    }

    /**
     * @param  array<string, mixed>  $evento
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    private function combatir(array $evento, array $ids, ContextoTickExploracion $contexto): array
    {
        $subCombates = [];
        $perdidoTotal = 0;
        $resultado = null;

        foreach ($ids as $pokemonId) {
            $salvaje = Pokemon::find($pokemonId);
            if ($salvaje === null) {
                continue;
            }

            $perdidoTotal += $this->descanso->aplicar($contexto);
            $resultado = $this->ejecutor->combatir($contexto, $salvaje, true);
            $subCombates[] = $this->subCombate($pokemonId, $resultado);

            if (! $resultado->victoria) {
                return $this->resultadoDerrota($evento, $resultado, $contexto, $subCombates, $perdidoTotal);
            }
        }

        if (! $resultado instanceof ResultadoBatallaExploracion) {
            return $this->resultadoSinSalvajes($evento, $perdidoTotal);
        }

        return $this->resultadoSuperada($evento, $resultado, $contexto, $subCombates, $perdidoTotal);
    }

    /**
     * @return array{pokemon_id: int, victoria: bool}
     */
    private function subCombate(int $pokemonId, ResultadoBatallaExploracion $resultado): array
    {
        return [
            'pokemon_id' => $pokemonId,
            'victoria' => $resultado->victoria,
        ];
    }

    /**
     * @param  array<string, mixed>  $evento
     * @param  list<array{pokemon_id: int, victoria: bool}>  $subCombates
     * @return array<string, mixed>
     */
    private function resultadoDerrota(
        array $evento,
        ResultadoBatallaExploracion $resultado,
        ContextoTickExploracion $contexto,
        array $subCombates,
        int $perdidoTotal,
    ): array {
        return array_merge($evento, $this->mapeador->mapear($resultado, 'derrota', $contexto->estado) + [
            'sub_combates' => $subCombates,
            'duration_loss' => $perdidoTotal,
            'derrota' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $evento
     * @return array<string, mixed>
     */
    private function resultadoSinSalvajes(array $evento, int $perdidoTotal): array
    {
        return array_merge($evento, [
            'resolucion' => 'derrota',
            'duration_loss' => $perdidoTotal,
            'derrota' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $evento
     * @param  list<array{pokemon_id: int, victoria: bool}>  $subCombates
     * @return array<string, mixed>
     */
    private function resultadoSuperada(
        array $evento,
        ResultadoBatallaExploracion $resultado,
        ContextoTickExploracion $contexto,
        array $subCombates,
        int $perdidoTotal,
    ): array {
        return array_merge($evento, $this->mapeador->mapear($resultado, 'superada', $contexto->estado) + [
            'sub_combates' => $subCombates,
            'duration_loss' => $perdidoTotal,
        ]);
    }

    /**
     * @param  array<string, mixed>  $evento
     * @return array<string, mixed>
     */
    private function evitada(array $evento): array
    {
        return array_merge($evento, [
            'resolucion' => 'evitada',
            'duration_loss' => 0,
            'evitada' => true,
        ]);
    }
}
