<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Src\Shared\Domain\Items\ClaveBonguri;
use Src\Shared\Domain\ValueObjects\MapaFactores;

/**
 * Datos de entrada para calcular el botín de una expedición.
 *
 * `nivelHabitat` y `duracionHoras` se aceptan pero todavía no alteran el
 * resultado. `bonguriHabitat` es el único bonguri que puede entregar la
 * expedición; `null` desactiva la tirada.
 *
 * `multiplicadorBotin` y `factorTiempo` se aplican ya en F1:
 * `multiplicadorBotin` escala la probabilidad del bonguri según el modo y
 * `factorTiempo` reduce el botín de las expediciones forzadas antes de tiempo.
 *
 * F3: `delHabitat` activa el bonus de Pokémon nativo (+25 % de tiradas de
 * baya y +10 pp de bonguri, con tope del 85 %). `factoresPlantacion` es un
 * mapa `baya:{slug}` => factor que solo afecta al peso de las bayas salvajes;
 * un mapa vacío deja el botín idéntico al de F1.
 */
final readonly class ContextoBotin
{
    public readonly MapaFactores $factoresPlantacion;

    public function __construct(
        public ModoExploracion $modo,
        public ?ClaveBonguri $bonguriHabitat,
        public int $nivelHabitat,
        public int $duracionHoras,
        public bool $delHabitat,
        public bool $huboDerrota,
        public float $multiplicadorBotin = 1.0,
        public float $factorTiempo = 1.0,
        ?MapaFactores $factoresPlantacion = null,
    ) {
        $this->factoresPlantacion = $factoresPlantacion ?? MapaFactores::desdeArray([]);
    }
}
