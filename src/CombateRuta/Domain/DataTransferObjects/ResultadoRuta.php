<?php

declare(strict_types=1);

namespace Src\CombateRuta\Domain\DataTransferObjects;

use Src\Exploraciones\Domain\Collections\RecompensaCapturaCollection;
use Src\Shared\Domain\Collections\ItemModalCollection;

/**
 * Resultado de un combate de ruta: la victoria devuelve las recompensas al
 * modal (exp total, exp por miembro, caramelos familia/EV/tipo y capturas).
 *
 * `victoria` a true solo cuando el jugador gana; la derrota no devuelve modal.
 * aArray() es frontera de presentación (contrato snake_case de la vista).
 */
final readonly class ResultadoRuta
{
    public function __construct(
        public readonly bool $victoria,
        public readonly int $expTotal,
        public readonly int $expMiembro,
        public readonly ItemModalCollection $caramelosFamilia,
        public readonly ItemModalCollection $caramelosEv,
        public readonly ItemModalCollection $caramelosTipo,
        public readonly RecompensaCapturaCollection $capturas,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato de la vista del modal.
     *
     * @return array{
     *     exp_total: int,
     *     exp_miembro: int,
     *     caramelos_familia: list<array{src: string, alt: string, cantidad: int, nombre: string|null}>,
     *     caramelos_ev: list<array{src: string, alt: string, cantidad: int, nombre: string|null}>,
     *     caramelos_tipo: list<array{src: string, alt: string, cantidad: int, nombre: string|null}>,
     *     capturas: list<array{pokemon_id: int, cantidad: int, es_shiny: bool}>,
     * }
     */
    public function aArray(): array
    {
        return [
            'exp_total' => $this->expTotal,
            'exp_miembro' => $this->expMiembro,
            'caramelos_familia' => $this->caramelosFamilia->toArray(),
            'caramelos_ev' => $this->caramelosEv->toArray(),
            'caramelos_tipo' => $this->caramelosTipo->toArray(),
            'capturas' => $this->capturas->toArray(),
        ];
    }
}
