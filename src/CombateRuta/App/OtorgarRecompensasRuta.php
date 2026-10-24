<?php

declare(strict_types=1);

namespace Src\CombateRuta\App;

use App\Enums\StatEnum;
use App\Jobs\ActualizarPokedexJob;
use App\Models\Pokemon;
use App\Models\Team;
use App\Models\User;
use App\Support\CadenasEvolutivas;
use App\Support\CrearReclutado;
use App\Support\ItemCatalogo;
use Random\Randomizer;
use Src\CombateRuta\Domain\DataTransferObjects\ResultadoRuta;
use Src\Exploraciones\App\NormalizadorPokemonDerrotado;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Domain\Collections\RecompensaCapturaCollection;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;
use Src\Exploraciones\Domain\Recompensas\RecompensaEv;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Pokemon\Domain\Stats\GeneradorIvs;
use Src\Shared\Domain\Collections\ItemModalCollection;
use Src\Shared\Domain\DataTransferObjects\ItemModal;
use Src\Shared\Domain\DataTransferObjects\ItemResuelto;
use Src\Shared\Domain\ProbabilidadCaptura;

/**
 * Otorga las recompensas de un combate de ruta ganado: la misma fórmula de
 * exploración con multiplicador 1.0 (sin el ×2 del entrenador), EXP a la
 * cuenta y a cada miembro del equipo, caramelos familia/EV/tipo, CAPTURAS de
 * los salvajes derrotados (ProbabilidadCaptura cap-25 con aleatorio
 * inyectable) y dispara el avistado de cada rival.
 *
 * Visionados SHINY (1/2048): captura garantizada → se crea el Reclutado al
 * instante con IVs máximos y aparecen en el modal con cantidad 1; quedan
 * excluidos del roll normal de captura.
 */
final class OtorgarRecompensasRuta
{
    public const MULTIPLICADOR_RUTA = 1.0;

    public function __construct(
        private readonly CalculadorRecompensas $calculador,
        private readonly PersistirRecompensas $persistir,
        private readonly CrearReclutado $crearReclutado,
    ) {
    }

    /**
     * @param  list<int>  $speciesIdsRival  ids de las especies derrotadas (team2)
     * @param  callable(): float|null  $aleatorio  fuente [0,1) del roll de captura
     * @param  array<int, bool>  $shinySlots  flags shiny alineados con $speciesIdsRival
     */
    public function otorgar(
        int $userId,
        int $teamId,
        array $speciesIdsRival,
        ?callable $aleatorio = null,
        array $shinySlots = [],
    ): ?ResultadoRuta {
        $usuario = User::find($userId);
        $equipo = Team::with('members.reclutado')->find($teamId);

        if ($usuario === null) {
            return null;
        }

        $soloIds = [];
        $soloShiny = [];
        foreach ($speciesIdsRival as $clave => $id) {
            if ($id > 0) {
                $soloIds[] = $id;
                $soloShiny[$clave] = ($shinySlots[$clave] ?? false) === true;
            }
        }

        if ($soloIds === []) {
            return null;
        }

        // Para la query, cadenas evolutivas y avistados: una entrada por especie.
        $ids = array_values(array_unique($soloIds));

        $pokemons = Pokemon::query()->with('stats', 'types');
        $pokemons->getQuery()->whereIn('id', $ids);
        $pokemons = $pokemons->get()->keyBy('id');

        if ($pokemons->isEmpty()) {
            return null;
        }

        $miembrosPorCadena = CadenasEvolutivas::miembrosDe($pokemons->pluck('evolution_chain_id'));

        // Una entrada por slot derrotado (conservando duplicados: 4× Magikarp = 4 entradas).
        $porSlot = collect($soloIds)
            ->map(fn (int $id): ?Pokemon => $pokemons->get($id))
            ->filter();

        if ($porSlot->isEmpty()) {
            return null;
        }

        $derrotados = NormalizadorPokemonDerrotado::normalizar($porSlot, $miembrosPorCadena);

        // Las claves de $derrotados son los índices de slot originales; ahí se
        // mira el flag shiny (los slots vacíos ya se filtraron arriba).
        $shinyDerrotados = $derrotados->filter(
            fn (PokemonDerrotado $pokemon, int $clave): bool => ($soloShiny[$clave] ?? false) === true
        );
        $normales = $derrotados->filter(
            fn (PokemonDerrotado $pokemon, int $clave): bool => ($soloShiny[$clave] ?? false) !== true
        );

        $recompensas = $this->calculador->calcular(
            $normales,
            $this->rollCaptura($aleatorio),
            $usuario->nivel(),
            self::MULTIPLICADOR_RUTA,
        );

        $this->persistir->persistir($recompensas, $equipo, $usuario);

        $this->despacharAvistados($ids, $userId);

        $capturasShiny = [];
        foreach ($shinyDerrotados as $derrotado) {
            $this->crearReclutado->crear(
                userId: $userId,
                pokemonId: $derrotado->id,
                ivs: GeneradorIvs::maximos(),
                esShiny: true,
            );
            $capturasShiny[] = new RecompensaCaptura(pokemonId: $derrotado->id, cantidad: 1, esShiny: true);
        }

        return new ResultadoRuta(
            victoria: true,
            expTotal: $recompensas->expTotal,
            expMiembro: $recompensas->expPorMiembro,
            caramelosFamilia: $this->caramelosFamiliaDe($recompensas),
            caramelosEv: $this->caramelosEvDe($recompensas),
            caramelosTipo: $this->caramelosTipoDe($recompensas),
            capturas: new RecompensaCapturaCollection($recompensas->capturas->concat($capturasShiny)->values()->all()),
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

    /**
     * Caramelos de familia agrupados al shape del modal
     * ({src, alt, cantidad, nombre}), resolviendo la clave canónica
     * `familia:{evolutionChainId}` con ItemCatalogo.
     */
    private function caramelosFamiliaDe(ResultadoRecompensas $recompensas): ItemModalCollection
    {
        return new ItemModalCollection(
            $recompensas->caramelosFamilia
                ->map(function (RecompensaFamilia $recompensa): ItemModal {
                    $resuelto = ItemCatalogo::resolve(ItemCatalogo::keyFamilia($recompensa->evolutionChainId));

                    return $this->itemModal($resuelto, $recompensa->cantidad, $resuelto->nombre);
                })
                ->values()
                ->all()
        );
    }

    /**
     * Caramelos EV agrupados al shape del modal; `nombre` es el label del stat
     * (StatEnum), null si el stat no aplica.
     */
    private function caramelosEvDe(ResultadoRecompensas $recompensas): ItemModalCollection
    {
        return new ItemModalCollection(
            $recompensas->caramelosEv
                ->map(function (RecompensaEv $recompensa): ItemModal {
                    $resuelto = ItemCatalogo::resolve(ItemCatalogo::keyEv($recompensa->stat));

                    return $this->itemModal($resuelto, $recompensa->cantidad, StatEnum::fromId($recompensa->stat)?->label());
                })
                ->values()
                ->all()
        );
    }

    /**
     * Caramelos de tipo agrupados al shape del modal; `nombre` es el label del tipo.
     */
    private function caramelosTipoDe(ResultadoRecompensas $recompensas): ItemModalCollection
    {
        return new ItemModalCollection(
            $recompensas->caramelosTipo
                ->map(fn (RecompensaTipo $recompensa): ItemModal => $this->itemModal(
                    ItemCatalogo::resolve(ItemCatalogo::keyTipo($recompensa->tipo)),
                    $recompensa->cantidad,
                    $recompensa->tipo,
                ))
                ->values()
                ->all()
        );
    }

    private function itemModal(ItemResuelto $resuelto, int $cantidad, ?string $nombre): ItemModal
    {
        return new ItemModal(
            src: $resuelto->imagen,
            alt: $resuelto->nombre,
            cantidad: $cantidad,
            nombre: $nombre,
        );
    }
}
