<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Stats;

/**
 * Stats base de un pokémon mapeados desde Eloquent (App\Models\Pokemon).
 *
 * Sustituye el array {hp, atk, def, spAtk, spDef, speed} que exponia
 * MapeadorPokemonBatalla::statsDe() (deuda F6). toArray() solo se usa en la
 * frontera (persistencia / contratos del motor de batalla).
 */
final readonly class DatosStats
{
    public function __construct(
        public readonly int $hp,
        public readonly int $atk,
        public readonly int $def,
        public readonly int $spAtk,
        public readonly int $spDef,
        public readonly int $speed,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato previo (claves snake_case/camelCase
     * del array devuelto por statsDe()).
     *
     * @return array{hp: int, atk: int, def: int, spAtk: int, spDef: int, speed: int}
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return [
            'hp' => $this->hp,
            'atk' => $this->atk,
            'def' => $this->def,
            'spAtk' => $this->spAtk,
            'spDef' => $this->spDef,
            'speed' => $this->speed,
        ];
    }
}
