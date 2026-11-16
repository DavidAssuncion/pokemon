<?php

declare(strict_types=1);

namespace Src\Avistamientos\Infra;

use App\Models\Habitat;
use App\Models\Pokedex;
use App\Models\Pokemon;
use Illuminate\Support\Facades\DB;
use Src\Avistamientos\Domain\DataTransferObjects\AvistamientoRegistrado;
use Src\Avistamientos\Domain\DataTransferObjects\EspecieEnHabitat;
use Src\Avistamientos\Domain\DataTransferObjects\EspeciesEnHabitatCollection;
use Src\Avistamientos\Domain\DataTransferObjects\EspeciesVistasCollection;
use Src\Avistamientos\Domain\DataTransferObjects\EspecieVista;
use Src\Avistamientos\Domain\DataTransferObjects\HabitatAvistamiento;
use Src\Avistamientos\Domain\ReglasAvistamiento;
use Src\Avistamientos\Domain\Repositories\AvistamientoRepositorioInterface;
use Src\Avistamientos\Domain\ValueObjects\FamiliaCandidata;
use Src\Avistamientos\Domain\ValueObjects\FamiliasCandidatasCollection;
use Src\Avistamientos\Domain\ValueObjects\HabitatCandidato;
use Src\Avistamientos\Domain\ValueObjects\HabitatsCandidatosCollection;
use Src\Avistamientos\Infra\Models\AvistamientoEvento;

/**
 * Persistencia Eloquent del evento global de avistamientos.
 *
 * Los instantes de la ventana se guardan como hora local de la aplicación
 * (`config('app.timezone')`, `Europe/Madrid`) con formato `Y-m-d H:i:s`.
 * La migración declara `timestamp` sin zona horaria y el cast `datetime` de
 * Laravel interpreta el valor almacenado en la zona de la aplicación; por
 * tanto, convertir a UTC al escribir desalinearía la lectura posterior.
 * `VentanaAvistamiento` ya produce los límites en `Europe/Madrid`, así que se
 * persisten y se releen en esa misma zona.
 *
 * La escritura es idempotente por `window_index`: `insertOrIgnore` deja ganar
 * la primera fila y la ventana nunca se actualiza.
 */
final class EloquentAvistamientoRepositorio implements AvistamientoRepositorioInterface
{
    public function buscarPorVentana(int $indice): ?AvistamientoRegistrado
    {
        $evento = AvistamientoEvento::query()
            ->where('window_index', $indice)
            ->first();

        if ($evento === null) {
            return null;
        }

        return new AvistamientoRegistrado(
            windowIndex: $evento->window_index,
            startsAt: $evento->starts_at->toDateTimeImmutable(),
            endsAt: $evento->ends_at->toDateTimeImmutable(),
            habitatId: $evento->habitat_id,
            evolutionChainId: $evento->evolution_chain_id,
            factor: $evento->factor,
        );
    }

    public function registrarSiNoExiste(AvistamientoRegistrado $registro): void
    {
        $ahora = now()->format('Y-m-d H:i:s');

        DB::table('avistamiento_eventos')->insertOrIgnore([
            'window_index' => $registro->windowIndex,
            'starts_at' => $registro->startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $registro->endsAt->format('Y-m-d H:i:s'),
            'habitat_id' => $registro->habitatId,
            'evolution_chain_id' => $registro->evolutionChainId,
            'factor' => $registro->factor,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    public function habitatsCandidatos(): HabitatsCandidatosCollection
    {
        $familiasElegibles = DB::table('pokemon_habitat')
            ->join('pokemon', 'pokemon.id', '=', 'pokemon_habitat.pokemon_id')
            ->select('pokemon_habitat.habitat_id')
            ->whereNotNull('pokemon.evolution_chain_id')
            ->whereNotIn('pokemon_habitat.habitat_id', ReglasAvistamiento::HABITATS_EXCLUIDOS)
            ->groupBy('pokemon_habitat.habitat_id', 'pokemon.evolution_chain_id')
            ->havingRaw('COUNT(*) >= ?', [ReglasAvistamiento::MIN_ESPECIES_FAMILIA]);

        $filas = DB::table('habitats')
            ->joinSub(
                $familiasElegibles,
                'familias_elegibles',
                'habitats.id',
                '=',
                'familias_elegibles.habitat_id',
            )
            ->select('habitats.id', 'habitats.name')
            ->distinct()
            ->orderBy('habitats.id')
            ->get();

        $items = [];
        foreach ($filas as $fila) {
            $items[] = new HabitatCandidato(
                id: (int) $fila->id,
                nombre: (string) $fila->name,
            );
        }

        return new HabitatsCandidatosCollection($items);
    }

    public function familiasCandidatas(
        int $habitatId,
        int $minimoEspecies = ReglasAvistamiento::MIN_ESPECIES_FAMILIA,
    ): FamiliasCandidatasCollection {
        $filas = DB::table('pokemon_habitat')
            ->join('pokemon', 'pokemon.id', '=', 'pokemon_habitat.pokemon_id')
            ->select('pokemon.evolution_chain_id')
            ->selectRaw('COUNT(*) AS numero_especies')
            ->where('pokemon_habitat.habitat_id', $habitatId)
            ->whereNotNull('pokemon.evolution_chain_id')
            ->groupBy('pokemon.evolution_chain_id')
            ->havingRaw('COUNT(*) >= ?', [$minimoEspecies])
            ->orderBy('pokemon.evolution_chain_id')
            ->get();

        $items = [];
        foreach ($filas as $fila) {
            $items[] = new FamiliaCandidata(
                evolutionChainId: (int) $fila->evolution_chain_id,
                numeroEspecies: (int) $fila->numero_especies,
            );
        }

        return new FamiliasCandidatasCollection($items);
    }

    public function habitat(int $habitatId): ?HabitatAvistamiento
    {
        $habitat = Habitat::query()->find($habitatId);

        if ($habitat === null) {
            return null;
        }

        return new HabitatAvistamiento(
            id: $habitat->id,
            nombre: $habitat->name,
        );
    }

    public function especiesDeFamilia(int $habitatId, int $evolutionChainId): EspeciesEnHabitatCollection
    {
        $filas = DB::table('pokemon_habitat')
            ->join('pokemon', 'pokemon.id', '=', 'pokemon_habitat.pokemon_id')
            ->where('pokemon_habitat.habitat_id', $habitatId)
            ->where('pokemon.evolution_chain_id', $evolutionChainId)
            ->orderBy('pokemon_habitat.level')
            ->orderBy('pokemon.species_id')
            ->get([
                'pokemon.id',
                'pokemon.name',
                'pokemon.species_id',
                'pokemon_habitat.level',
            ]);

        $items = [];
        foreach ($filas as $fila) {
            $items[] = new EspecieEnHabitat(
                pokemonId: (int) $fila->id,
                nombre: (string) $fila->name,
                speciesId: (int) $fila->species_id,
                nivel: (int) $fila->level,
            );
        }

        return new EspeciesEnHabitatCollection($items);
    }

    public function nombreGlobalDeFamilia(int $evolutionChainId): ?string
    {
        $primero = Pokemon::query()
            ->where('evolution_chain_id', $evolutionChainId)
            ->get(['name', 'species_id'])
            ->sortBy('species_id')
            ->first();

        return $primero?->name;
    }

    public function vistosDelJugador(
        int $userId,
        EspeciesEnHabitatCollection $especies,
        bool $desbloqueoTodo = false,
    ): EspeciesVistasCollection {
        /** @var array<int, true> $vistos */
        $vistos = [];

        if (! $desbloqueoTodo) {
            $pokemonIds = $especies->pluck(
                static fn (EspecieEnHabitat $especie): int => $especie->pokemonId,
            );

            if ($pokemonIds !== []) {
                // Se ignora el scope global por usuario y se filtra con el
                // `$userId` recibido, que puede no coincidir con `Auth::id()`
                // (jobs y procesos en segundo plano).
                $idsVistos = Pokedex::query()
                    ->withoutGlobalScope(Pokedex::USER_SCOPE_NAME)
                    ->where('user_id', $userId)
                    ->toBase()
                    ->whereIn('pokemon_id', $pokemonIds)
                    ->where('visto', true)
                    ->pluck('pokemon_id')
                    ->map(static fn (mixed $pokemonId): int => (int) $pokemonId)
                    ->all();

                foreach ($idsVistos as $idVisto) {
                    $vistos[$idVisto] = true;
                }
            }
        }

        $items = [];
        foreach ($especies as $especie) {
            $items[] = new EspecieVista(
                pokemonId: $especie->pokemonId,
                visto: $desbloqueoTodo || isset($vistos[$especie->pokemonId]),
            );
        }

        return new EspeciesVistasCollection($items);
    }
}
