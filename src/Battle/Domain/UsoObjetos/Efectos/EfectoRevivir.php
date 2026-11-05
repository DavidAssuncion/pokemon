<?php

declare(strict_types=1);

namespace Src\Battle\Domain\UsoObjetos\Efectos;

use InvalidArgumentException;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ObjetivosItem;
use Src\Battle\Domain\UsoObjetos\EfectoItem;
use Src\Battle\Domain\UsoObjetos\ResultadoEfectoItem;

/**
 * Revive a un aliado debilitado con una fracción de sus PS máximos.
 */
final readonly class EfectoRevivir implements EfectoItem
{
    public function __construct(
        private float $fraccion,
        private bool $restaurarUsos = false,
    ) {
        if ($fraccion <= 0.0 || $fraccion > 1.0) {
            throw new InvalidArgumentException('La fracción de revivir debe estar entre 0 y 1.');
        }
    }

    public function clave(): string
    {
        return 'revivir';
    }

    public function objetivos(): ObjetivosItem
    {
        return ObjetivosItem::ALIADO_DEBILITADO;
    }

    public function validar(Combatiente $objetivo): ?string
    {
        if ($objetivo->estaVivo()) {
            return "{$objetivo->nombre()} no está debilitado.";
        }

        return null;
    }

    public function aplicar(Combatiente $objetivo, Combatiente $actor): ResultadoEfectoItem
    {
        if (! $objetivo->revivir($this->fraccion)) {
            return new ResultadoEfectoItem(
                texto: "{$objetivo->nombre()} no pudo ser revivido.",
            );
        }

        if ($this->restaurarUsos) {
            $objetivo->restaurarUsos();
        }

        return new ResultadoEfectoItem(
            texto: "{$objetivo->nombre()} revivió con ".((int) round($objetivo->hpActual())).' PS.',
            curado: (int) round($objetivo->hpActual()),
            revivido: true,
        );
    }
}
