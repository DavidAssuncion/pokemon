<?php

declare(strict_types=1);

namespace App\Support\Pc;

/**
 * Leyenda completa de IV/EV y stats anónimos del rival.
 *
 * Incluye el perfil aplicado, las cuatro opciones de perfil y los stats de
 * cada Pokémon rival. No contiene nombres, especies, tipos ni movimientos.
 */
final readonly class LeyendaIvsEvs
{
    /**
     * @param  list<PerfilIvsEvsOpcion>  $perfiles
     * @param  list<PokemonRivalPreview>  $pokemons
     */
    public function __construct(
        public ?PerfilRivalAplicado $actual,
        public array $perfiles,
        public array $pokemons,
    ) {
    }

    /**
     * @return array{
     *     actual: array{clave: string, etiqueta: string}|null,
     *     perfiles: list<array{
     *         clave: string,
     *         etiqueta: string,
     *         descripcion: string,
     *         stats_al_maximo: int,
     *         iv_maximo: int,
     *         iv_resto: int,
     *         ev_maximo: int,
     *         ev_resto: int,
     *         actual: bool
     *     }>,
     *     pokemons: list<array{
     *         etiqueta: string,
     *         stats: list<array{
     *             clave: string,
     *             etiqueta: string,
     *             base: int,
     *             iv: int,
     *             ev: int,
     *             calculado: int
     *         }>
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'actual' => $this->actual?->toArray(),
            'perfiles' => array_map(
                static fn (PerfilIvsEvsOpcion $perfil): array => $perfil->toArray(),
                $this->perfiles,
            ),
            'pokemons' => array_map(
                static fn (PokemonRivalPreview $pokemon): array => $pokemon->toArray(),
                $this->pokemons,
            ),
        ];
    }
}
