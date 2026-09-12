<?php

declare(strict_types=1);

namespace Src\Exploraciones\Presentation;

/**
 * Un objeto perdido por derrota en expedición: tipo de recompensa,
 * identificador del ítem (chain id, stat, slug de tipo o pokemon_id),
 * label descriptivo y cantidad perdida.
 *
 * Generado por FinalizarExploracionHandler::objetosPerdidos() y
 * serializado a eventos['objetos_perdidos'].
 */
final class DTOObjetoPerdido
{
    public function __construct(
        public readonly string $tipo,
        public readonly int|string $id,
        public readonly ?string $label,
        public readonly int $cantidad_perdida,
    ) {
    }

    /**
     * @return array{tipo: string, id: int|string, label: string|null, cantidad_perdida: int}
     */
    public function toArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'id' => $this->id,
            'label' => $this->label,
            'cantidad_perdida' => $this->cantidad_perdida,
        ];
    }
}
