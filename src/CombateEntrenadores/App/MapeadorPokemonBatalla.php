<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Enums\StatEnum;
use App\Models\Pokemon;
use App\Models\PokemonType;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Mapea un pokémon de la BD (App\Models\Pokemon) a los datos de combate
 * (DatosPokemonBatalla) que consume el motor de batalla. Temporalmente
 * genera los movimientos a partir de los tipos (sin datos reales de ataques).
 */
class MapeadorPokemonBatalla
{
    public function __construct(
        private readonly GeneradorMovimientosTipo $generadorMovimientos,
    ) {
    }

    public function desdePokemon(
        Pokemon $pokemon,
        string $id,
        string $nombre,
        Posicion $posicion,
        bool $shiny = false,
        ?string $item = null,
    ): DatosPokemonBatalla {
        $stats = $this->statsDe($pokemon);
        $tipos = $this->tiposDe($pokemon);

        $movimientos = [];
        foreach ($this->generadorMovimientos->generar($tipos) as $m) {
            $movimientos[] = new MovimientoBatalla(
                nombre: $m['nombre'],
                potencia: $m['potencia'],
                tipo: $m['tipo'],
                categoria: CategoriaMovimiento::from($m['categoria']),
            );
        }

        return new DatosPokemonBatalla(
            id: $id,
            nombre: $nombre,
            hp: $stats['hp'],
            atk: $stats['atk'],
            def: $stats['def'],
            spAtk: $stats['spAtk'],
            spDef: $stats['spDef'],
            speed: $stats['speed'],
            tipos: $tipos,
            posicion: $posicion,
            moves: $movimientos,
            shiny: $shiny,
            iconName: '',
            effectKeys: [],
            item: $item,
            speciesId: (int) $pokemon->id,
            formSuffix: '',
        );
    }

    /**
     * @return array{hp: int, atk: int, def: int, spAtk: int, spDef: int, speed: int}
     */
    public function statsDe(Pokemon $pokemon): array
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

        return $stats;
    }

    /**
     * @return list<TipoPokemon>
     */
    public function tiposDe(Pokemon $pokemon): array
    {
        return $pokemon->types
            ->map(fn (PokemonType $tipo): TipoPokemon => TipoPokemon::from($tipo->type->value))
            ->values()
            ->all();
    }
}
