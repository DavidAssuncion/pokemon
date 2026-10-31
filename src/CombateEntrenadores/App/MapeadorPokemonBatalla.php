<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Enums\StatEnum;
use App\Models\Pokemon;
use App\Models\PokemonType;
use Src\Battle\Domain\Catalog\CatalogoHabilidades;
use Src\Battle\Domain\Catalog\DepuradorMovimientos;
use Src\Battle\Domain\Collections\MovimientoBatallaCollection;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\Exceptions\MovimientoNoExiste;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\Battle\Domain\Repositories\MovimientoRepository;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\Pokemon\Domain\Stats\DatosStats;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Collections\StringCollection;
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
    private const MAX_MOVIMIENTOS_RESPALDO = 8;

    public function __construct(
        private readonly GeneradorMovimientosTipo $generadorMovimientos,
        private readonly ?MovimientoRepository $movimientoRepository = null,
        private readonly DepuradorMovimientos $depurador = new DepuradorMovimientos(),
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
            moves: $movimientos->all(),
            shiny: $shiny,
            iconName: '',
            effectKeys: $this->efectosDe($pokemon, $habilidadActivaId)->toList(),
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
     * movimiento ofensivo asignado. Antes de devolverlos, elimina los
     * redundantes (mismo tipo/categoría/efectos/precisión) conservando el de
     * mayor potencia.
     *
     * @param  list<string>|null  $seleccionados
     */
    public function movimientosDeConFallback(
        Pokemon $pokemon,
        TiposCollection $tipos,
        ?array $seleccionados = null,
        ?int $nivelDesbloqueo = null,
    ): MovimientoBatallaCollection {
        $movimientos = $seleccionados !== null && $seleccionados !== []
            ? $this->movimientosDeSeleccion($seleccionados)
            : $this->movimientosDe($pokemon, $nivelDesbloqueo);

        $movimientos = $this->depurador->depurar($movimientos);

        foreach ($movimientos as $movimiento) {
            if (! $movimiento->esEstado()) {
                return $movimientos;
            }
        }

        foreach ($this->generadorMovimientos->generar($tipos) as $m) {
            $movimientos->add(new MovimientoBatalla(
                nombre: $m->nombre,
                potencia: $m->potencia,
                tipo: $m->tipo,
                categoria: $m->categoria,
            ));
        }

        return $movimientos;
    }

    /**
     * Resuelve una selección explícita de slugs (loadout del jugador o set
     * manual de gimnasio). El controlador valida el nivel antes de guardar;
     * aquí no se filtra para no romper los sets manuales de gimnasio.
     *
     * @param  array<int, mixed>  $slugs
     */
    public function movimientosDeSeleccion(array $slugs): MovimientoBatallaCollection
    {
        if ($this->movimientoRepository === null) {
            return new MovimientoBatallaCollection();
        }

        $movimientos = [];
        foreach ($slugs as $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }

            try {
                $movimientos[] = $this->movimientoRepository
                    ->obtenerPorSlug($slug)
                    ->aMovimientoBatalla();
            } catch (MovimientoNoExiste) {
                // Un slug retirado no debe romper la batalla; el controlador
                // valida el loadout antes de guardarlo.
            }
        }

        return new MovimientoBatallaCollection($movimientos);
    }

    public function depurarMovimientos(MovimientoBatallaCollection $movimientos): MovimientoBatallaCollection
    {
        return $this->depurador->depurar($movimientos);
    }

    /**
     * Movimientos de nivel de la especie (incluida su línea de preevoluciones)
     * disponibles al nivel indicado. Los salvajes y entrenadores llevan todos
     * los que aprenden; las MT/tutores/huevos no entran por defecto.
     */
    public function movimientosDe(Pokemon $pokemon, ?int $nivelDesbloqueo = null): MovimientoBatallaCollection
    {
        if ($this->movimientoRepository === null) {
            return new MovimientoBatallaCollection();
        }

        $pokemon->loadMissing('moves');

        $movimientos = [];
        foreach ($pokemon->moves as $move) {
            if (($move->pivot->source ?? null) !== 'level-up') {
                continue;
            }

            $nivelMovimiento = $move->pivot->level ?? null;
            if ($nivelDesbloqueo !== null
                && $nivelMovimiento !== null
                && (int) $nivelMovimiento > $nivelDesbloqueo) {
                continue;
            }

            try {
                $movimientos[] = $this->movimientoRepository
                    ->obtenerPorSlug($move->slug)
                    ->aMovimientoBatalla();
            } catch (MovimientoNoExiste) {
                // Un movimiento retirado del catálogo no debe romper la batalla.
            }
        }

        if ($movimientos !== []) {
            return new MovimientoBatallaCollection($movimientos);
        }

        // Especies sin movimientos de nivel (Abra, Unown, Ditto, Smeargle…):
        // se usa una selección corta de MT/tutor como respaldo para no dejarlas
        // con el fallback sintético de tipo Normal.
        return $this->movimientosDeRespaldo($pokemon, $nivelDesbloqueo);
    }

    /**
     * Respaldo para especies sin level-up disponible: las 8 mejores MT/tutor
     * legales al nivel. El depurador posterior elimina redundantes.
     */
    private function movimientosDeRespaldo(Pokemon $pokemon, ?int $nivelDesbloqueo): MovimientoBatallaCollection
    {
        if ($this->movimientoRepository === null) {
            return new MovimientoBatallaCollection();
        }

        $movimientos = [];
        foreach ($pokemon->moves as $move) {
            if (! in_array($move->pivot->source ?? null, ['machine', 'tutor', 'custom'], true)) {
                continue;
            }

            if ($nivelDesbloqueo !== null && $move->nivelRequeridoEfectivo() > $nivelDesbloqueo) {
                continue;
            }

            try {
                $movimientos[] = $this->movimientoRepository
                    ->obtenerPorSlug($move->slug)
                    ->aMovimientoBatalla();
            } catch (MovimientoNoExiste) {
                // Un movimiento retirado del catálogo no debe romper la batalla.
            }
        }

        $stats = $this->statsDe($pokemon);
        $tipos = $this->tiposDe($pokemon);
        $prefiereEspecial = $stats->spAtk > $stats->atk;

        usort(
            $movimientos,
            fn (MovimientoBatalla $a, MovimientoBatalla $b): int => $this->puntuarRespaldo($b, $prefiereEspecial, $tipos)
                <=> $this->puntuarRespaldo($a, $prefiereEspecial, $tipos),
        );

        return new MovimientoBatallaCollection(
            array_slice($movimientos, 0, self::MAX_MOVIMIENTOS_RESPALDO),
        );
    }

    private function puntuarRespaldo(
        MovimientoBatalla $movimiento,
        bool $prefiereEspecial,
        TiposCollection $tipos,
    ): float {
        if ($movimiento->esEstado()) {
            return 0.0;
        }

        $puntuacion = max(1, $movimiento->potencia)
            * max(0.3, $movimiento->perfil->precision);

        if (($movimiento->esEspecial() && $prefiereEspecial)
            || ($movimiento->esFisico() && ! $prefiereEspecial)) {
            $puntuacion *= 1.5;
        }

        if ($tipos->contiene($movimiento->tipo)) {
            $puntuacion *= 1.5;
        }

        if ($movimiento->priority > 0) {
            $puntuacion += $movimiento->priority * 10;
        }

        if ($movimiento->perfil->recoilFraccion > 0) {
            $puntuacion *= 0.9;
        }

        return $puntuacion;
    }

    /**
     * Claves de efecto de todas las habilidades equipadas. Las habilidades
     * personalizadas pueden aportar varias claves (p. ej. inmunidad + boost).
     */
    public function efectosDe(Pokemon $pokemon, ?int $habilidadActivaId = null): StringCollection
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

        return new StringCollection(array_values($claves));
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
