<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Enums\StatEnum;
use App\Models\Pokemon;
use App\Models\PokemonType;
use Src\Battle\Domain\Catalog\CatalogoHabilidades;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\Exceptions\MovimientoNoExiste;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Battle\Domain\Repositories\MovimientoRepository;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\Pokemon\Domain\Stats\DatosStats;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TipoPokemon;
use Src\Shared\Tipos\TiposCollection;

/**
 * Mapea un pokémon de la BD (App\Models\Pokemon) a los datos de combate
 * (DatosPokemonBatalla) que consume el motor de batalla. Usa el catálogo
 * real (`pokemon_moves`) y añade el generador sintético por tipos cuando la
 * especie no tiene ningún movimiento ofensivo asignado. Las habilidades se
 * resuelven por `pokemon.abilities` y sus claves de efecto.
 */
class MapeadorPokemonBatalla
{
    public function __construct(
        private readonly GeneradorMovimientosTipo $generadorMovimientos,
        private readonly ?MovimientoRepository $movimientoRepository = null,
    ) {
    }

    /**
     * @param  list<string>|null  $movimientosSeleccionados
     */
    public function desdePokemon(
        Pokemon $pokemon,
        string $id,
        string $nombre,
        Posicion $posicion,
        bool $shiny = false,
        ?string $item = null,
        ?int $nivel = null,
        ?StatsValue $evs = null,
        ?StatsValue $ivs = null,
        PosturaCombate $postura = PosturaCombate::ATACAR,
        ?array $movimientosSeleccionados = null,
        ?int $habilidadActivaId = null,
        ?int $nivelDesbloqueo = null,
    ): DatosPokemonBatalla {
        $stats = $this->statsDe($pokemon);

        $tipos = $this->tiposDe($pokemon);

        $movimientos = $this->movimientosDeConFallback(
            $pokemon,
            $tipos,
            $movimientosSeleccionados,
            $nivelDesbloqueo ?? $nivel,
        );

        return new DatosPokemonBatalla(
            id: $id,
            nombre: $nombre,
            hp: $stats->hp,
            atk: $stats->atk,
            def: $stats->def,
            spAtk: $stats->spAtk,
            spDef: $stats->spDef,
            speed: $stats->speed,
            tipos: $tipos->toList(),
            posicion: $posicion,
            moves: $movimientos,
            shiny: $shiny,
            iconName: '',
            effectKeys: $this->efectosDe($pokemon, $habilidadActivaId),
            item: $item,
            speciesId: (int) $pokemon->id,
            formSuffix: '',
            nivel: $nivel,
            evs: $evs,
            ivs: $ivs,
            postura: $postura,
            peso: max(0.0, (float) $pokemon->weight / 10.0),
        );
    }

    /**
     * Movimientos del catálogo + fallback sintético si no hay ningún
     * movimiento ofensivo asignado.
     *
     * @param  list<string>|null  $seleccionados
     * @return list<MovimientoBatalla>
     */
    public function movimientosDeConFallback(
        Pokemon $pokemon,
        TiposCollection $tipos,
        ?array $seleccionados = null,
        ?int $nivelDesbloqueo = null,
    ): array {
        $movimientos = $seleccionados !== null && $seleccionados !== []
            ? $this->movimientosDeSeleccion($seleccionados, $nivelDesbloqueo)
            : $this->movimientosDe($pokemon, $nivelDesbloqueo);

        foreach ($movimientos as $movimiento) {
            if (! $movimiento->esEstado()) {
                return $movimientos;
            }
        }

        foreach ($this->generadorMovimientos->generar($tipos) as $m) {
            $movimientos[] = new MovimientoBatalla(
                nombre: $m->nombre,
                potencia: $m->potencia,
                tipo: $m->tipo,
                categoria: $m->categoria,
            );
        }

        return $movimientos;
    }

    /**
     * Resuelve una selección de slugs del loadout del reclutado.
     *
     * @param  array<int, mixed>  $slugs
     * @return list<MovimientoBatalla>
     */
    public function movimientosDeSeleccion(array $slugs, ?int $nivelDesbloqueo = null): array
    {
        if ($this->movimientoRepository === null) {
            return [];
        }

        $movimientos = [];
        foreach ($slugs as $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }

            try {
                $movimiento = $this->movimientoRepository
                    ->obtenerPorSlug($slug)
                    ->aMovimientoBatalla();
                if ($nivelDesbloqueo === null || $movimiento->nivelRequerido <= $nivelDesbloqueo) {
                    $movimientos[] = $movimiento;
                }
            } catch (MovimientoNoExiste) {
                // Un slug retirado no debe romper la batalla; el controlador
                // valida el loadout antes de guardarlo.
            }
        }

        return $movimientos;
    }

    /**
     * Movimientos reales asignados a la especie. Si no tiene ninguno, el
     * llamador conserva el fallback sintético por tipos.
     *
     * @return list<MovimientoBatalla>
     */
    public function movimientosDe(Pokemon $pokemon, ?int $nivelDesbloqueo = null): array
    {
        if ($this->movimientoRepository === null) {
            return [];
        }

        $pokemon->loadMissing('moves');

        $movimientos = [];
        foreach ($pokemon->moves as $move) {
            try {
                $movimiento = $this->movimientoRepository
                    ->obtenerPorSlug($move->slug)
                    ->aMovimientoBatalla();
                if ($nivelDesbloqueo === null || $movimiento->nivelRequerido <= $nivelDesbloqueo) {
                    $movimientos[] = $movimiento;
                }
            } catch (MovimientoNoExiste) {
                // Un movimiento retirado del catálogo no debe romper la batalla.
            }
        }

        return $movimientos;
    }

    /**
     * Claves de efecto de todas las habilidades equipadas. Las habilidades
     * personalizadas pueden aportar varias claves (p. ej. inmunidad + boost).
     *
     * @return list<string>
     */
    public function efectosDe(Pokemon $pokemon, ?int $habilidadActivaId = null): array
    {
        $pokemon->loadMissing('abilities');

        $habilidades = $habilidadActivaId !== null
            ? $pokemon->abilities->where('id', $habilidadActivaId)
            : $pokemon->abilities;

        // Si el loadout apunta a una habilidad retirada, no dejar al Pokémon
        // sin ninguna habilidad: se vuelve al conjunto de la especie.
        if ($habilidadActivaId !== null && $habilidades->isEmpty()) {
            $habilidades = $pokemon->abilities;
        }

        $claves = [];
        foreach ($habilidades as $ability) {
            $effectKeys = $ability->data['effect_keys'] ?? null;
            $resueltas = is_array($effectKeys) && $effectKeys !== []
                ? array_values(array_filter($effectKeys, 'is_string'))
                : CatalogoHabilidades::clavesDe($ability->name);

            foreach ($resueltas as $clave) {
                $claves[$clave] = $clave;
            }
        }

        return array_values($claves);
    }

    public function statsDe(Pokemon $pokemon): DatosStats
    {
        $stats = ['hp' => 0, 'atk' => 0, 'def' => 0, 'spAtk' => 0, 'spDef' => 0, 'speed' => 0];

        foreach ($pokemon->stats as $stat) {
            $clave = match ($stat->stat) {
                StatEnum::HP => 'hp',
                StatEnum::ATTACK => 'atk',
                StatEnum::DEFENSE => 'def',
                StatEnum::SPECIAL_ATTACK => 'spAtk',
                StatEnum::SPECIAL_DEFENSE => 'spDef',
                StatEnum::SPEED => 'speed',
            };
            $stats[$clave] = (int) $stat->base_stat;
        }

        return new DatosStats(
            hp: $stats['hp'],
            atk: $stats['atk'],
            def: $stats['def'],
            spAtk: $stats['spAtk'],
            spDef: $stats['spDef'],
            speed: $stats['speed'],
        );
    }

    public function tiposDe(Pokemon $pokemon): TiposCollection
    {
        return new TiposCollection(
            $pokemon->types
                ->map(fn (PokemonType $tipo): TipoPokemon => TipoPokemon::from($tipo->type->value))
                ->values()
                ->all()
        );
    }
}
