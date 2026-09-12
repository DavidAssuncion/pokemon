<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Jobs\ActualizarPokedexJob;
use App\Models\Pokemon;
use App\Models\Team;
use App\Models\User;
use App\Support\CadenasEvolutivas;
use App\Support\ItemCatalogo;
use Src\CombateEntrenadores\Domain\Collections\ItemCarameloCollection;
use Src\CombateEntrenadores\Domain\DataTransferObjects\DatosModalVictoria;
use Src\CombateEntrenadores\Domain\DataTransferObjects\ItemCaramelo;
use Src\Exploraciones\App\NormalizadorPokemonDerrotado;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;

/**
 * Otorga las recompensas de un combate contra entrenador ganado: DOBLE de lo
 * que daría una exploración por los mismos derrotados (misma fórmula), con
 * EXP a la cuenta y a cada miembro del equipo, caramelos familia/EV/tipo, y
 * dispara el evento de avistados de los pokémon rivales.
 *
 * Sin capturas: el combate contra entrenadores no recluta rivales.
 */
final class OtorgarRecompensasEntrenador
{
    /**
     * Multiplicador aplicado al mismo cálculo de una exploración (2× para
     * entrenadores normales, 10× para gimnasios).
     */
    public const MULTIPLICADOR_ENTRENADOR = 2.0;

    public const MULTIPLICADOR_GIMNASIO = 10.0;

    public function __construct(
        private readonly CalculadorRecompensas $calculador,
        private readonly PersistirRecompensas $persistir,
    ) {
    }

    /**
     * @param  list<int>  $speciesIdsRival  ids de las especies derrotadas (team2)
     * @param  float  $multiplicador  multiplicador sobre la fórmula de exploración
     *                                (MULTIPLICADOR_ENTRENADOR, MULTIPLICADOR_GIMNASIO)
     */
    public function otorgar(int $userId, int $teamId, array $speciesIdsRival, int $nivelEntrenador, float $multiplicador = self::MULTIPLICADOR_ENTRENADOR): ?DatosModalVictoria
    {
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

        // Combate de entrenadores: nunca hay captura del rival.
        $aleatorioCaptura = static fn (PokemonDerrotado $pokemon): bool => false;

        $nivelSalvaje = $usuario->nivel();

        $recompensas = $this->calculador->calcular(
            $derrotados,
            $aleatorioCaptura,
            $nivelSalvaje,
            $multiplicador,
        );

        $this->persistir->persistir($recompensas, $equipo, $usuario);

        $this->despacharAvistados($ids, $userId);

        return $this->aDatosModal($recompensas);
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

    /**
     * Datos de presentación para el modal de victoria.
     */
    private function aDatosModal(ResultadoRecompensas $recompensas): DatosModalVictoria
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

        return new DatosModalVictoria(
            expTotal: $recompensas->expTotal,
            expMiembro: $recompensas->expPorMiembro,
            caramelos: $caramelos,
        );
    }
}
