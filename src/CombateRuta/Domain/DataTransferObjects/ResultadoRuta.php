<?php

declare(strict_types=1);

namespace Src\CombateRuta\Domain\DataTransferObjects;

use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;

/**
 * Resultado de un combate de ruta: la victoria devuelve las recompensas al
 * modal (exp total, exp por miembro, caramelos familia/EV/tipo y capturas).
 *
 * `victoria` a true solo cuando el jugador gana; la derrota no devuelve modal.
 * aArray() es frontera de presentación (contrato snake_case de la vista).
 */
final readonly class ResultadoRuta
{
    /**
     * @param  list<array{src: string, alt: string, cantidad: int, nombre: string|null}>  $caramelosFamilia
     * @param  list<array{src: string, alt: string, cantidad: int, nombre: string|null}>  $caramelosEv
     * @param  list<array{src: string, alt: string, cantidad: int, nombre: string|null}>  $caramelosTipo
     * @param  list<RecompensaCaptura>  $capturas
     */
    public function __construct(
        public readonly bool $victoria,
        public readonly int $expTotal,
        public readonly int $expMiembro,
        public readonly array $caramelosFamilia,
        public readonly array $caramelosEv,
        public readonly array $caramelosTipo,
        public readonly array $capturas,
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
     *     capturas: list<array{pokemon_id: int, cantidad: int}>,
     * }
     */
    public function aArray(): array
    {
        return [
            'exp_total' => $this->expTotal,
            'exp_miembro' => $this->expMiembro,
            'caramelos_familia' => $this->caramelosFamilia,
            'caramelos_ev' => $this->caramelosEv,
            'caramelos_tipo' => $this->caramelosTipo,
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
