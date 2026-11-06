<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Perdidas;

/**
 * Objeto perdido por derrota en una expedición: tipo de recompensa,
 * identificador (chain id, stat, slug de tipo, pokemon_id o clave de item),
 * etiqueta descriptiva y cantidad perdida.
 */
final readonly class ObjetoPerdido
{
    public function __construct(
        public string $tipo,
        public int|string $id,
        public ?string $label,
        public int $cantidad,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato persistido de `objetos_perdidos`.
     *
     * @return array{tipo: string, id: int|string, label: string|null, cantidad_perdida: int}
     */
    public function aArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'id' => $this->id,
            'label' => $this->label,
            'cantidad_perdida' => $this->cantidad,
        ];
    }
}
