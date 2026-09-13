<?php

declare(strict_types=1);

namespace Src\CombateRuta\App;

use App\Jobs\ActualizarPokedexJob;
use App\Models\Pokemon;
use App\Models\Team;
use App\Models\User;
use App\Support\CadenasEvolutivas;
use App\Support\ItemCatalogo;
use Random\Randomizer;
use Src\CombateEntrenadores\Domain\Collections\ItemCarameloCollection;
use Src\CombateEntrenadores\Domain\DataTransferObjects\ItemCaramelo;
use Src\CombateRuta\Domain\DataTransferObjects\ResultadoRuta;
use Src\Exploraciones\App\NormalizadorPokemonDerrotado;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Shared\Domain\ProbabilidadCaptura;

/**
 * Otorga las recompensas de un combate de ruta ganado: la misma fórmula de
 * exploración con multiplicador 1.0 (sin el ×2 del entrenador), EXP a la
 * cuenta y a cada miembro del equipo, caramelos familia/EV/tipo, CAPTURAS de
 * los salvajes derrotados (ProbabilidadCaptura cap-25 con aleatorio
 * inyectable) y dispara el avistado de cada rival.
 */
final class OtorgarRecompensasRuta
{
    public const MULTIPLICADOR_RUTA = 1.0;

    public function __construct(
        private readonly CalculadorRecompensas $calculador,
        private readonly PersistirRecompensas $persistir,
    ) {
    }

    /**
     * @param  list<int>  $speciesIdsRival  ids de las especies derrotadas (team2)
     * @param  callable(): float|null  $aleatorio  fuente [0,1) del roll de captura
     */
    public function otorgar(
        int $userId,
        int $teamId,
        array $speciesIdsRival,
        int $nivelRival,
        ?callable $aleatorio = null,
    ): ?ResultadoRuta {
        $usuario = User::find($userId);
        $equipo = Team::with('members.reclutado')->find($teamId);

        if ($usuario === null) {
            return null;
        }

        $ids = array_values(array_unique(array_filter(
            $speciesIdsRival,
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return null;
        }

        $pokemons = Pokemon::query()->with('stats', 'types');
        $pokemons->getQuery()->whereIn('id', $ids);
        $pokemons = $pokemons->get()->keyBy('id');

        if ($pokemons->isEmpty()) {
            return null;
        }

        $miembrosPorCadena = CadenasEvolutivas::miembrosDe($pokemons->pluck('evolution_chain_id'));
        $derrotados = NormalizadorPokemonDerrotado::normalizar($pokemons, $miembrosPorCadena);

        $recompensas = $this->calculador->calcular(
            $derrotados,
            $this->rollCaptura($aleatorio),
            $usuario->nivel(),
            self::MULTIPLICADOR_RUTA,
        );

        $this->persistir->persistir($recompensas, $equipo, $usuario);

        $this->despacharAvistados($ids, $userId);

        return new ResultadoRuta(
            victoria: true,
            expTotal: $recompensas->expTotal,
            expMiembro: $recompensas->expPorMiembro,
            caramelos: $this->caramelosDe($recompensas),
            capturas: $recompensas->capturas,
        );
    }

    /**
     * Roll de captura por derrotado con la regla compartida cap-25
     * (ProbabilidadCaptura::intentar) y aleatorio [0,1) inyectable.
     *
     * @param  callable(): float|null  $aleatorio
     * @return callable(PokemonDerrotado): bool
     */
    private function rollCaptura(?callable $aleatorio): callable
    {
        $aleatorio ??= $this->aleatorioAzar();

        return static fn (PokemonDerrotado $pokemon): bool => ProbabilidadCaptura::intentar($pokemon->captureRate, $aleatorio);
    }

    /**
     * @return callable(): float
     */
    private function aleatorioAzar(): callable
    {
        $randomizer = new Randomizer();

        return static fn (): float => $randomizer->getFloat(0.0, 1.0);
    }

    /**
     * @param  list<int>  $pokemonIds
     */
    private function despacharAvistados(array $pokemonIds, int $userId): void
    {
        foreach ($pokemonIds as $pokemonId) {
            ActualizarPokedexJob::dispatch($userId, $pokemonId, 'AVISTADO');
        }
    }

    private function caramelosDe(ResultadoRecompensas $recompensas): ItemCarameloCollection
    {
        $caramelos = new ItemCarameloCollection();

        foreach ($recompensas->caramelosFamilia as $recompensa) {
            $resuelto = ItemCatalogo::resolve(ItemCatalogo::keyFamilia($recompensa->evolutionChainId));
            $caramelos->add(new ItemCaramelo(
                nombre: $resuelto['nombre'],
                imagen: $resuelto['imagen'],
                cantidad: $recompensa->cantidad,
            ));
        }

        foreach ($recompensas->caramelosEv as $recompensa) {
            $resuelto = ItemCatalogo::resolve(ItemCatalogo::keyEv($recompensa->stat));
            $caramelos->add(new ItemCaramelo(
                nombre: $resuelto['nombre'],
                imagen: $resuelto['imagen'],
                cantidad: $recompensa->cantidad,
            ));
        }

        foreach ($recompensas->caramelosTipo as $recompensa) {
            $caramelos->add(new ItemCaramelo(
                nombre: $recompensa->tipo,
                imagen: '/images/candy_type/'.$recompensa->slug().'.webp',
                cantidad: $recompensa->cantidad,
            ));
        }

        return $caramelos;
    }
}
