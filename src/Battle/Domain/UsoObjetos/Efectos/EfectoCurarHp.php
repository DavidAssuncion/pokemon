<?php

declare(strict_types=1);

namespace Src\Battle\Domain\UsoObjetos\Efectos;

use InvalidArgumentException;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ObjetivosItem;
use Src\Battle\Domain\UsoObjetos\EfectoItem;
use Src\Battle\Domain\UsoObjetos\ResultadoEfectoItem;

/**
 * Cura un porcentaje fijo de los PS máximos del objetivo vivo.
 */
final readonly class EfectoCurarHp implements EfectoItem
{
    public function __construct(
        private float $porcentaje,
    ) {
        if ($porcentaje <= 0.0 || $porcentaje > 1.0) {
            throw new InvalidArgumentException('El porcentaje de curación debe estar entre 0 y 1.');
        }
    }

    public function clave(): string
    {
        return 'curar_hp';
    }

    public function objetivos(): ObjetivosItem
    {
        return ObjetivosItem::ALIADO_VIVO;
    }

    public function validar(Combatiente $objetivo): ?string
    {
        if (! $objetivo->estaVivo()) {
            return "{$objetivo->nombre()} está debilitado.";
        }

        if ($objetivo->hpActual() >= $this->hpMaximo($objetivo)) {
            return "{$objetivo->nombre()} ya tiene los PS al máximo.";
        }

        return null;
    }

    public function aplicar(Combatiente $objetivo, Combatiente $actor): ResultadoEfectoItem
    {
        $curado = $objetivo->curarHpCantidad($this->hpMaximo($objetivo) * $this->porcentaje);

        return new ResultadoEfectoItem(
            texto: "{$objetivo->nombre()} recuperó ".((int) round($curado)).' PS.',
            curado: (int) round($curado),
        );
    }

    private function hpMaximo(Combatiente $objetivo): float
    {
        return max(1.0, $objetivo->pokemon()->battleStats()->hp);
    }
}
