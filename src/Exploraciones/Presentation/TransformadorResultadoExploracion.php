<?php

declare(strict_types=1);

namespace Src\Exploraciones\Presentation;

use App\Models\Pokemon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;
use Src\Exploraciones\Domain\Recompensas\RecompensaEv;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\RecompensaItem;
use Src\Exploraciones\Domain\Recompensas\RecompensaItemCollection;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;

/**
 * Transforma el ResultadoRecompensas en el contrato JSON de eventos['resultado']
 * que persiste la exploración (formato consumido por la página de exploraciones).
 */
final class TransformadorResultadoExploracion
{
    /**
     * @param  Collection<int, Pokemon>  $pokemons  Derrotados cargados con stats y types.
     * @param  array<int, Collection<int, Pokemon>>|null  $miembrosPorCadena  Mapa de TODOS los
     *                                              miembros de cada familia, keyed por
     *                                              evolution_chain_id (columna).
     * @param  array{encuentros: int, victorias: int, huidas: int, emboscadas: int, contratiempos: int}  $incidentes
     * @param  array{del_habitat?: bool}  $bonos
     * @return array{
     *     capturados: list<array{pokemon_id: int, nombre: string, cantidad: int, es_shiny: bool}>,
     *     caramelos_familia: list<array{evolution_chain_id: int, nombre: string|null, pokemon_id: int|null, cantidad: int}>,
     *     caramelos_ev: list<array{stat: int, cantidad: int}>,
     *     caramelos_tipo: list<array{tipo: string, slug: string, cantidad: int}>,
     *     items: list<array{clave: string, cantidad: int}>,
     *     exp: int,
     *     exp_reclutado: int,
     *     resultado: string,
     *     duration_real: int,
     *     tiempo_perdido: int,
     *     incidentes: array{encuentros: int, victorias: int, huidas: int, emboscadas: int, contratiempos: int},
     *     bonos: array{del_habitat: bool},
     * }
     */
    public function desde(
        ResultadoRecompensas $recompensas,
        Collection $pokemons,
        ?array $miembrosPorCadena = null,
        string $categoria = 'exito',
        int $durationReal = 0,
        int $tiempoPerdido = 0,
        array $incidentes = ['encuentros' => 0, 'victorias' => 0, 'huidas' => 0, 'emboscadas' => 0, 'contratiempos' => 0],
        array $bonos = [],
    ): array {
        return [
            'capturados' => $this->capturados($recompensas->capturas, $pokemons),
            'caramelos_familia' => $this->caramelosFamilia($recompensas->caramelosFamilia, $pokemons, $miembrosPorCadena),
            'caramelos_ev' => $this->caramelosEv($recompensas->caramelosEv),
            'caramelos_tipo' => $this->caramelosTipo($recompensas->caramelosTipo),
            'items' => $this->items($recompensas->items),
            'exp' => $recompensas->expTotal,
            'exp_reclutado' => $recompensas->expPorMiembro,
            'resultado' => $categoria,
            'duration_real' => $durationReal,
            'tiempo_perdido' => $tiempoPerdido,
            'incidentes' => $incidentes,
            'bonos' => ['del_habitat' => $bonos['del_habitat'] ?? false],
        ];
    }

    /**
     * @param  BaseCollection<int, RecompensaCaptura>  $capturas
     * @param  Collection<int, Pokemon>  $pokemons
     * @return list<array{pokemon_id: int, nombre: string, cantidad: int, es_shiny: bool}>
     */
    private function capturados(BaseCollection $capturas, Collection $pokemons): array
    {
        return $capturas
            ->sortBy('pokemonId')
            ->map(function (RecompensaCaptura $captura) use ($pokemons): array {
                $pokemon = $pokemons->get($captura->pokemonId);

                return [
                    'pokemon_id' => $captura->pokemonId,
                    'nombre' => $pokemon->name ?? '',
                    'cantidad' => $captura->cantidad,
                    'es_shiny' => $captura->esShiny,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  BaseCollection<int, RecompensaFamilia>  $caramelos
     * @param  Collection<int, Pokemon>  $pokemons
     * @param  array<int, Collection<int, Pokemon>>|null  $miembrosPorCadena
     * @return list<array{evolution_chain_id: int, nombre: string|null, pokemon_id: int|null, cantidad: int}>
     */
    private function caramelosFamilia(BaseCollection $caramelos, Collection $pokemons, ?array $miembrosPorCadena): array
    {
        return $caramelos
                ->sortBy('evolutionChainId')
                ->map(function (RecompensaFamilia $caramelo) use ($pokemons, $miembrosPorCadena): array {
                    $base = $this->pokemonBaseDeCadena($caramelo->evolutionChainId, $pokemons, $miembrosPorCadena);

                    return [
                        'evolution_chain_id' => $caramelo->evolutionChainId,
                        'nombre' => $base?->name,
                        'pokemon_id' => $base?->id,
                        'cantidad' => $caramelo->cantidad,
                    ];
                })
                ->values()
                ->all();
    }

    /**
     * Miembro base de la familia (menor species_id) de TODOS sus miembros; si no
     * hay mapa (o la cadena no está), fallback a los derrotados de esa cadena
     * ordenados por species_id (mismo criterio: menor species_id, determinista).
     * Es el pokémon que identifica a la familia (imagen candy_pokemon/{id}.webp).
     *
     * @param  Collection<int, Pokemon>  $pokemons
     * @param  array<int, Collection<int, Pokemon>>|null  $miembrosPorCadena
     */
    private function pokemonBaseDeCadena(
        int $evolutionChainId,
        Collection $pokemons,
        ?array $miembrosPorCadena,
    ): ?Pokemon {
        $miembros = $miembrosPorCadena[$evolutionChainId] ?? null;

        if ($miembros !== null && $miembros->isNotEmpty()) {
            return $miembros->sortBy('species_id')->first();
        }

        return $pokemons
            ->sortBy('species_id')
            ->first(
                fn (Pokemon $pokemon): bool => (int) $pokemon->evolution_chain_id === $evolutionChainId
            );
    }

    /**
     * @param  BaseCollection<int, RecompensaEv>  $caramelos
     * @return list<array{stat: int, cantidad: int}>
     */
    private function caramelosEv(BaseCollection $caramelos): array
    {
        return $caramelos
            ->sortBy('stat')
            ->map(fn (RecompensaEv $caramelo): array => [
                'stat' => $caramelo->stat,
                'cantidad' => $caramelo->cantidad,
            ])
            ->values()
            ->all();
    }

    /**
     * Botín persistido: solo clave y cantidad. Los nombres e iconos se
     * resuelven en presentación con el catálogo compartido.
     *
     * @return list<array{clave: string, cantidad: int}>
     */
    private function items(RecompensaItemCollection $items): array
    {
        return $items
            ->sort(static fn (RecompensaItem $a, RecompensaItem $b): int => $a->clave <=> $b->clave)
            ->map(static fn (RecompensaItem $item): array => [
                'clave' => $item->clave,
                'cantidad' => $item->cantidad,
            ]);
    }

    /**
     * @param  BaseCollection<int, RecompensaTipo>  $caramelos
     * @return list<array{tipo: string, slug: string, cantidad: int}>
     */
    private function caramelosTipo(BaseCollection $caramelos): array
    {
        return $caramelos
            ->sortBy('tipo')
            ->map(fn (RecompensaTipo $caramelo): array => [
                'tipo' => $caramelo->tipo,
                'slug' => $caramelo->slug(),
                'cantidad' => $caramelo->cantidad,
            ])
            ->values()
            ->all();
    }
}
