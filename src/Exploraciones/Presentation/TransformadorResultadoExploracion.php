<?php

declare(strict_types=1);

namespace Src\Exploraciones\Presentation;

use App\Models\Pokemon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;
use Src\Exploraciones\Domain\Recompensas\RecompensaEv;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;

/**
 * Transforma el ResultadoRecompensas en el contrato JSON de eventos['resultado']
 * que persiste la exploración (formato consumido por la página de exploraciones).
 */
final class TransformadorResultadoExploracion
{
    /**
     * @param  Collection<int, Pokemon>  $pokemons  Derrotados cargados con evolutionChain.pokemon.
     * @return array{
     *     avistados: list<array{pokemon_id: int, nombre: string}>,
     *     capturados: list<array{pokemon_id: int, nombre: string, cantidad: int}>,
     *     caramelos_familia: list<array{evolution_chain_id: int, nombre: string|null, cantidad: int}>,
     *     caramelos_ev: list<array{stat: int, cantidad: int}>,
     *     caramelos_tipo: list<array{tipo: string, slug: string, cantidad: int}>,
     *     exp: int,
     * }
     */
    public function desde(ResultadoRecompensas $recompensas, Collection $pokemons): array
    {
        return [
            'avistados' => $this->avistados($pokemons),
            'capturados' => $this->capturados($recompensas->capturas, $pokemons),
            'caramelos_familia' => $this->caramelosFamilia($recompensas->caramelosFamilia, $pokemons),
            'caramelos_ev' => $this->caramelosEv($recompensas->caramelosEv),
            'caramelos_tipo' => $this->caramelosTipo($recompensas->caramelosTipo),
            'exp' => $recompensas->expTotal,
        ];
    }

    /**
     * @param  Collection<int, Pokemon>  $pokemons
     * @return list<array{pokemon_id: int, nombre: string}>
     */
    private function avistados(Collection $pokemons): array
    {
        return $pokemons
            ->sortBy('id')
            ->map(fn (Pokemon $pokemon): array => [
                'pokemon_id' => $pokemon->id,
                'nombre' => $pokemon->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  BaseCollection<int, RecompensaCaptura>  $capturas
     * @param  Collection<int, Pokemon>  $pokemons
     * @return list<array{pokemon_id: int, nombre: string, cantidad: int}>
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
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  BaseCollection<int, RecompensaFamilia>  $caramelos
     * @param  Collection<int, Pokemon>  $pokemons
     * @return list<array{evolution_chain_id: int, nombre: string|null, cantidad: int}>
     */
    private function caramelosFamilia(BaseCollection $caramelos, Collection $pokemons): array
    {
        return $caramelos
            ->sortBy('evolutionChainId')
            ->map(function (RecompensaFamilia $caramelo) use ($pokemons): array {
                return [
                    'evolution_chain_id' => $caramelo->evolutionChainId,
                    'nombre' => $this->nombreBaseDeCadena($caramelo->evolutionChainId, $pokemons),
                    'cantidad' => $caramelo->cantidad,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Nombre del pokémon base de la cadena (menor species_id) entre los derrotados.
     *
     * @param  Collection<int, Pokemon>  $pokemons
     */
    private function nombreBaseDeCadena(int $evolutionChainId, Collection $pokemons): ?string
    {
        $deLaCadena = $pokemons->first(
            fn (Pokemon $pokemon): bool => (int) $pokemon->evolution_chain_id === $evolutionChainId
        );

        return $deLaCadena?->evolutionChain?->pokemon
            ->sortBy('species_id')
            ->first()
            ?->name;
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
