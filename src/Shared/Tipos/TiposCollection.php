<?php

declare(strict_types=1);

namespace Src\Shared\Tipos;

use Src\Shared\Domain\Collection;
use Src\Shared\Domain\SlugTipo;

/**
 * Colección tipada de TipoPokemon. Dominio puro: no mapea desde Eloquent;
 * los mapeadores (F6) construyen TiposCollection desde los valores de tipo.
 */
class TiposCollection extends Collection
{
    public string $type = TipoPokemon::class;

    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /**
     * Frontera: valores enteros de los tipos.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        return $this->pluck(fn (TipoPokemon $tipo) => $tipo->value);
    }

    /**
     * Nombres de los tipos en español (mensajes de dominio y frontera).
     *
     * @return list<string>
     */
    public function nombres(): array
    {
        return $this->map(fn (TipoPokemon $tipo) => $tipo->label());
    }

    /**
     * Frontera: slugs ASCII minúscula (assets candy_type/, iconos).
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        return $this->map(fn (TipoPokemon $tipo) => SlugTipo::de($tipo->label()));
    }

    /**
     * Verifica si la colección contiene el tipo dado.
     */
    public function contiene(TipoPokemon $tipo): bool
    {
        return $this->contains(fn (TipoPokemon $existente) => $existente === $tipo);
    }
}
