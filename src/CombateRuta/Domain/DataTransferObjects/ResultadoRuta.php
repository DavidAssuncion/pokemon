<?php

declare(strict_types=1);

namespace Src\CombateRuta\Domain\DataTransferObjects;

use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;
use Src\Shared\Domain\Collections\ItemCarameloCollection;
use Src\Shared\Domain\DataTransferObjects\ItemCaramelo;

/**
 * Resultado de un combate de ruta: la victoria devuelve las recompensas al
 * modal (exp total, exp por miembro, caramelos y capturas de salvajes).
 *
 * `victoria` a true solo cuando el jugador gana; la derrota no devuelve modal.
 * toArray() es frontera de presentación (contrato snake_case de la vista).
 */
final readonly class ResultadoRuta
{
    public ItemCarameloCollection $caramelos;

    /** @var list<RecompensaCaptura> */
    public array $capturas;

    /**
     * @param  ItemCarameloCollection|array<int, ItemCaramelo>  $caramelos
     * @param  list<RecompensaCaptura>  $capturas
     */
    public function __construct(
        public readonly bool $victoria,
        public readonly int $expTotal,
        public readonly int $expMiembro,
        ItemCarameloCollection|array $caramelos,
        array $capturas,
    ) {
        $this->caramelos = $caramelos instanceof ItemCarameloCollection
            ? $caramelos
            : new ItemCarameloCollection($caramelos);
        $this->capturas = $capturas;
    }

    /**
     * Frontera — shape exacto del contrato de la vista del modal.
     *
     * @return array{
     *     exp_total: int,
     *     exp_miembro: int,
     *     caramelos: list<array{nombre: string, imagen: string, cantidad: int}>,
     *     capturas: list<array{pokemon_id: int, cantidad: int}>,
     * }
     */
    public function aArray(): array
    {
        return [
            'exp_total' => $this->expTotal,
            'exp_miembro' => $this->expMiembro,
            'caramelos' => $this->caramelos->map(
                fn (ItemCaramelo $caramelo): array => [
                    'nombre' => $caramelo->nombre,
                    'imagen' => $caramelo->imagen,
                    'cantidad' => $caramelo->cantidad,
                ],
            ),
            'capturas' => array_map(
                fn (RecompensaCaptura $captura): array => [
                    'pokemon_id' => $captura->pokemonId,
                    'cantidad' => $captura->cantidad,
                ],
                $this->capturas,
            ),
        ];
    }
}
