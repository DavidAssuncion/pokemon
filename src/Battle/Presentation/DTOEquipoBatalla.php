<?php

declare(strict_types=1);

namespace Src\Battle\Presentation;

use Src\Battle\Domain\Posicion;
use Src\Pokemon\Domain\PokemonEntity;

/**
 * DTO para los datos de un equipo de batalla.
 * Reemplaza el array asociativo sin contrato.
 */
class DTOEquipoBatalla
{
    /**
     * @param  array<int, array{pokemon: PokemonEntity, posicion: Posicion}>  $miembros
     */
    public function __construct(
        public readonly array $miembros,
    ) {
    }
}
