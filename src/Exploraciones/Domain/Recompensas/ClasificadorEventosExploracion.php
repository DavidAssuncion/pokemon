<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Recompensas;

use Src\Exploraciones\Domain\ValueObjects\ColeccionEventosExploracion;
use Src\Exploraciones\Domain\ValueObjects\EventoExploracion;

/**
 * Clasifica la bitácora tipada de una expedición en derrotados, avistados y
 * hallazgos. Dominio puro: no conoce Eloquent ni la persistencia.
 */
final class ClasificadorEventosExploracion
{
    /**
     * IDs de pokémon derrotados: solo eventos con resolución victoria (o sin
     * resolución, retrocompat RF-07), expandidos por pokemon_id/pokemon_ids.
     * Las emboscadas evitadas quedan fuera.
     *
     * @return list<int>
     */
    public function idsDerrotados(ColeccionEventosExploracion $bitacora): array
    {
        $ids = [];
        foreach ($bitacora as $evento) {
            if (! $evento->esVictoria() || $evento->esEmboscadaEvitada()) {
                continue;
            }

            foreach ($evento->pokemonIds()->all() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * IDs de pokémon avistados: todo evento con pokemon_id(s) (encuentro,
     * emboscada, huida y legacy 'pokemon').
     *
     * @return list<int>
     */
    public function idsAvistados(ColeccionEventosExploracion $bitacora): array
    {
        $ids = [];
        foreach ($bitacora as $evento) {
            if (! $evento->esAvistamiento()) {
                continue;
            }

            foreach ($evento->pokemonIds()->all() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Eventos de hallazgo más el hallazgo equivalente de cada emboscada
     * evitada.
     */
    public function hallazgos(ColeccionEventosExploracion $bitacora): ColeccionEventosExploracion
    {
        return new ColeccionEventosExploracion([
            ...$bitacora->filter(fn (EventoExploracion $evento): bool => $evento->esHallazgo())->toList(),
            ...$this->hallazgosEmboscadasEvitadas($bitacora),
        ]);
    }

    /**
     * Cada emboscada evitada produce un hallazgo equivalente de 1 caramelo de
     * familia del primer pokémon implicado.
     *
     * @return list<EventoExploracion>
     */
    private function hallazgosEmboscadasEvitadas(ColeccionEventosExploracion $bitacora): array
    {
        $hallazgos = [];
        foreach ($bitacora as $evento) {
            if (! $evento->esEmboscadaEvitada()) {
                continue;
            }

            $ids = $evento->pokemonIds()->all();
            if ($ids !== []) {
                $hallazgos[] = $this->hallazgoDeEmboscadaEvitada($ids[0]);
            }
        }

        return $hallazgos;
    }

    /**
     * Hallazgo equivalente de una emboscada evitada: 1 caramelo de familia del
     * primer pokémon implicado (origen 'emboscada_evitada').
     */
    private function hallazgoDeEmboscadaEvitada(int $pokemonId): EventoExploracion
    {
        return EventoExploracion::desdeArray([
            'tipo' => 'hallazgo',
            'subtype' => 'caramelo_familia',
            'pokemon_id' => $pokemonId,
            'cantidad' => 1,
            'origen' => 'emboscada_evitada',
        ]);
    }
}
