<?php

declare(strict_types=1);

namespace Src\Gimnasios\App;

use App\Models\Pokemon;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;
use Src\Gimnasios\Domain\EvsRangoEntrenador;
use Src\Shared\Domain\IvsRangoEntrenador;
use Src\Shared\Tipos\TiposCollection;

/**
 * Construye el equipo rival de un gimnasio a partir del EquipoEtapaGimnasio
 * (vanguardia | retaguardia) definido en el catálogo. Pasa el nivel rival a
 * DatosPokemonBatalla para que BattleStats calcule los stats de combate (HP,
 * ataque, etc.) al nivel correcto.
 *
 * Las posiciones se respetan literalmente del DTO: vanguardia → VANGUARDIA,
 * retaguardia → RETAGUARDIA (no se reclasifica por stats).
 *
 * Los EVs y los IVs se reparten según los stats base del pokémon: los 2 mejores
 * stats reciben $evPrincipal/$ivPrincipal y los 4 restantes $evResto/$ivResto.
 */
final class GeneradorPokemonGimnasio
{
    public function __construct(
        private readonly MapeadorPokemonBatalla $mapeador,
    ) {
    }

    /**
     * @return list<DatosPokemonBatalla>
     */
    public function generar(
        EquipoEtapaGimnasio $equipo,
        int $nivelRival,
        int $evPrincipal = 0,
        int $evResto = 0,
        int $ivPrincipal = 0,
        int $ivResto = 0,
    ): array {
        $equipoRival = [];
        $indice = 0;

        foreach ($this->porPosicion($equipo) as [$posicion, $pokemon]) {
            $statsBase = $this->mapeador->statsDe($pokemon);
            $evs = EvsRangoEntrenador::distribuir($evPrincipal, $evResto, $statsBase);
            $ivs = IvsRangoEntrenador::distribuir($ivPrincipal, $ivResto, $statsBase);

            $equipoRival[] = new DatosPokemonBatalla(
                id: "gimnasio_rival_{$indice}",
                nombre: $pokemon->name,
                hp: $statsBase->hp,
                atk: $statsBase->atk,
                def: $statsBase->def,
                spAtk: $statsBase->spAtk,
                spDef: $statsBase->spDef,
                speed: $statsBase->speed,
                tipos: $this->mapeador->tiposDe($pokemon)->toList(),
                posicion: $posicion,
                moves: $this->movimientosDe($pokemon, $this->mapeador->tiposDe($pokemon), $nivelRival),
                shiny: false,
                iconName: '',
                effectKeys: $this->mapeador->efectosDe($pokemon),
                item: null,
                speciesId: $pokemon->id,
                formSuffix: '',
                nivel: $nivelRival,
                evs: $evs,
                ivs: $ivs,
                peso: max(0.0, (float) $pokemon->weight / 10.0),
            );

            $indice++;
        }

        return $equipoRival;
    }

    /**
     * Recorre vanguardia y retaguardia en orden, cargando cada species_id con
     * su posición. Permite duplicados (cada id del catálogo genera un
     * combatiente); si una especie no existe en BD, se omite.
     *
     * Escalado 3v3: el roster se normaliza a exactamente 3 combatientes
     * (menos de 3 → repite cíclicamente; más de 3 → recorta a los 3 primeros;
     * vacío → vacío). El orden vanguardia → retaguardia se preserva, por lo que
     * la formación escalada mantiene ambos bandos cuando el catálogo los tiene.
     *
     * @return list<array{Posicion, Pokemon}>
     */
    private function porPosicion(EquipoEtapaGimnasio $equipo): array
    {
        $resultado = [];

        foreach ([
            [Posicion::VANGUARDIA, $equipo->vanguardia->all()],
            [Posicion::RETAGUARDIA, $equipo->retaguardia->all()],
        ] as [$posicion, $speciesIds]) {
            foreach ($this->cargarPokemons($speciesIds) as $pokemon) {
                $resultado[] = [$posicion, $pokemon];
            }
        }

        return $this->escalarATres($resultado);
    }

    /**
     * Garantiza exactamente 3 combatientes preservando el orden
     * vanguardia → retaguardia original:
     * - roster vacío → [];
     * - roster > 3 → recorta a los 3 primeros;
     * - roster < 3 → repite cíclicamente hasta 3.
     *
     * @param  list<array{Posicion, Pokemon}>  $roster
     * @return list<array{Posicion, Pokemon}>
     */
    private function escalarATres(array $roster): array
    {
        $tamaño = count($roster);

        if ($tamaño === 0) {
            return [];
        }

        if ($tamaño > 3) {
            return array_slice($roster, 0, 3);
        }

        $indice = $tamaño;

        while (count($roster) < 3) {
            $roster[] = $roster[$indice % $tamaño];
            $indice++;
        }

        return $roster;
    }

    /**
     * Carga los pokémon de la BD respetando el orden exacto de $speciesIds.
     *
     * @param  list<int>  $speciesIds
     * @return list<Pokemon>
     */
    private function cargarPokemons(array $speciesIds): array
    {
        $ids = array_values(array_filter($speciesIds, static fn (int $id): bool => $id > 0));

        if ($ids === []) {
            return [];
        }

        /** @var array<int, Pokemon> $mapa */
        $mapa = Pokemon::query()
            ->with('stats', 'types')
            ->whereIn('species_id', $ids)
            ->get()
            ->keyBy('species_id')
            ->all();

        $resultado = [];
        foreach ($ids as $speciesId) {
            if (isset($mapa[$speciesId])) {
                $resultado[] = $mapa[$speciesId];
            }
        }

        return $resultado;
    }

    /**
     * @return list<\Src\Battle\Domain\MovimientoBatalla>
     */
    private function movimientosDe(Pokemon $pokemon, TiposCollection $tipos, int $nivel): array
    {
        return $this->mapeador->movimientosDeConFallback($pokemon, $tipos, null, $nivel);
    }
}
