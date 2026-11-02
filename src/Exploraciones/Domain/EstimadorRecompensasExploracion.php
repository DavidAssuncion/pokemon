<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Src\Exploraciones\Domain\ValueObjects\ColeccionStatsDelPool;
use Src\Exploraciones\Domain\ValueObjects\PoolHabitat;
use Src\Shared\Domain\NivelHelper;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Estima las recompensas esperadas de una exploración antes de lanzarla
 * (RF-D del Analista). Dominio puro: no depende de Eloquent ni de HTTP.
 *
 * El pool recibido tiene la misma forma que PoolHabitat de
 * ProcesarExploracionHandler, añadiendo por cada pokémon:
 *   base_experience: int, evolution_chain_id: int|null, species_id: int.
 *
 * Sin aleatoriedad real: calcula valores nominales deterministas sobre el
 * promedio del pool y devuelve un rango [min, max] aplicando rango_min/max.
 */
final class EstimadorRecompensasExploracion
{
    /** Minutos entre encuentros base; fuente única en ReglasExploracion. */
    public const MINUTOS_POR_ENCUENTRO = ReglasExploracion::MINUTOS_POR_ENCUENTRO;

    public const RANGO_MIN = 0.75;

    public const RANGO_MAX = 1.25;

    /**
     * Frontera — estima sobre el shape previo del pool (arrays).
     *
     * @param  list<array{id:int, capture_rate:int, hatch:int|null, tipos:list<TipoPokemon>, stats:list<array{stat:int,effort:int}>, base_experience:int, evolution_chain_id:?int, species_id:int}>  $pool
     * @param  array<int, array<int, int>|list<int>>  $cadenas  etapa por species_id o, en
     *                                              cadenas sin aristas, lista legacy de especies
     * @return array{e:int, items:list<array{tipo:string,label:string,min:int,max:int}>, por_horas:int, rango_min:float, rango_max:float, aviso:string}
     *
     * @deprecated Frontera (BC con tests unitarios que pasan arrays). El dominio
     * recomienda estimarDePool() con PoolHabitat.
     */
    public function estimar(
        array $pool,
        int $porHoras,
        CapacidadesStats $capacidades,
        int $dificultad,
        int $nivelSalvaje,
        array $cadenas = [],
    ): array {
        return $this->estimarDePool(
            PoolHabitat::desdeArray($pool),
            $porHoras,
            $capacidades,
            $dificultad,
            $nivelSalvaje,
            $cadenas,
        );
    }

    /**
     * @param  array<int, array<int, int>|list<int>>  $cadenas  etapa por species_id o, en
     *                                              cadenas sin aristas, lista legacy de especies
     * @return array{e:int, items:list<array{tipo:string,label:string,min:int,max:int}>, por_horas:int, rango_min:float, rango_max:float, aviso:string}
     */
    public function estimarDePool(
        PoolHabitat $pool,
        int $porHoras,
        CapacidadesStats $capacidades,
        int $dificultad,
        int $nivelSalvaje,
        array $cadenas = [],
    ): array {
        $minutos = max(1, $porHoras) * 60;

        $intervaloEfectivo = max(1, (int) floor(
            self::MINUTOS_POR_ENCUENTRO * (1 - $capacidades->reduccionIntervaloMovilidad($dificultad)),
        ));

        $e = intdiv($minutos, $intervaloEfectivo)
            + $capacidades->bonusEventosExploracion($dificultad);

        [$expPromedio, $familiaPromedio, $evPromedio] = $this->nominalesPromedio($pool, $nivelSalvaje, $cadenas);

        // El factor económico (0.5) se aplica UNA sola vez sobre el nominal;
        // la presentación y PersistirRecompensas no vuelven a descontar.
        $factor = ReglasExploracion::FACTOR_RECOMPENSAS;

        $items = [
            $this->item('familia', $e * $familiaPromedio * $factor, 'Caramelos de familia'),
            $this->item('ev', $e * $evPromedio * $factor, 'Caramelos EV'),
            $this->item(
                'tipo',
                $e * (int) floor(($expPromedio * 0.2) / 100) * $factor,
                'Caramelos de tipo '.TipoPokemon::from($this->tipoDominante($pool))->label(),
            ),
        ];

        return [
            'e' => $e,
            'items' => $items,
            'por_horas' => $porHoras,
            'rango_min' => self::RANGO_MIN,
            'rango_max' => self::RANGO_MAX,
            'aviso' => 'Estimación basada en el promedio del pool del hábitat y nivel elegido.',
        ];
    }

    /**
     * @param  array<int, array<int, int>|list<int>>  $cadenas  etapa por species_id o, en
     *                                              cadenas sin aristas, lista legacy de especies
     * @return array{int, float, float} [expPromedio, familiaPromedio, evPromedio]
     */
    private function nominalesPromedio(PoolHabitat $pool, int $nivelSalvaje, array $cadenas): array
    {
        if ($pool->isEmpty()) {
            return [0, 0.0, 0.0];
        }

        $conteo = $pool->tamano();
        $exp = 0;
        $familia = 0;
        $ev = 0;

        foreach ($pool as $pokemon) {
            $exp += NivelHelper::expDerrota($pokemon->baseExperience, $nivelSalvaje);

            $ev += $this->sumaEffort($pokemon->stats);

            $familia += $this->fase($pokemon->speciesId, $pokemon->evolutionChainId, $cadenas);
        }

        return [
            intdiv($exp, $conteo),
            $familia / $conteo,
            $ev / $conteo,
        ];
    }

    /** @return array{tipo: string, label: string, min: int, max: int} */
    private function item(string $tipo, float $nominal, string $label): array
    {
        $min = (int) floor($nominal * self::RANGO_MIN);
        $max = (int) ceil($nominal * self::RANGO_MAX);

        return [
            'tipo' => $tipo,
            'label' => $label,
            'min' => $min,
            'max' => $max,
        ];
    }

    private function sumaEffort(ColeccionStatsDelPool $stats): float
    {
        $total = 0.0;
        foreach ($stats as $stat) {
            $total += $stat->effort;
        }

        return $total;
    }

    /**
     * Fase de la cadena a la que pertenece la especie. Admite el mapa real de
     * etapas (`species_id => etapa`) y la lista legacy de especies ordenadas.
     * Si no hay datos de cadena se simplifica a 1.
     *
     * @param  array<int, array<int, int>|list<int>>  $cadenas
     */
    private function fase(int $speciesId, ?int $evolutionChainId, array $cadenas): float
    {
        if ($evolutionChainId === null || ! isset($cadenas[$evolutionChainId])) {
            return 1.0;
        }

        $miembros = $cadenas[$evolutionChainId];

        if (! array_is_list($miembros)) {
            return isset($miembros[$speciesId]) ? (float) $miembros[$speciesId] : 1.0;
        }

        $indice = array_search($speciesId, $miembros, true);

        return $indice === false ? 1.0 : (float) ($indice + 1);
    }

    /**
     * Tipo más frecuente del pool (para la etiqueta del caramelo de tipo).
     * NORMAL si el pool está vacío o no aporta tipos.
     */
    private function tipoDominante(PoolHabitat $pool): int
    {
        if ($pool->isEmpty()) {
            return TipoPokemon::NORMAL->value;
        }

        $frecuencias = [];
        foreach ($pool as $pokemon) {
            foreach ($pokemon->tipos->toList() as $tipo) {
                $frecuencias[$tipo->value] = ($frecuencias[$tipo->value] ?? 0) + 1;
            }
        }

        if ($frecuencias === []) {
            return TipoPokemon::NORMAL->value;
        }

        arsort($frecuencias);

        return (int) array_key_first($frecuencias);
    }
}
