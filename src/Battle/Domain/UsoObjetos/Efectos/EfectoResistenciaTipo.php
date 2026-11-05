<?php

declare(strict_types=1);

namespace Src\Battle\Domain\UsoObjetos\Efectos;

use InvalidArgumentException;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ObjetivosItem;
use Src\Battle\Domain\UsoObjetos\EfectoItem;
use Src\Battle\Domain\UsoObjetos\ResultadoEfectoItem;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Reduce temporalmente el daño recibido de un tipo. Reaplicar refresca la
 * duración sin acumular el factor.
 */
final readonly class EfectoResistenciaTipo implements EfectoItem
{
    public function __construct(
        private TipoPokemon $tipo,
        private float $factor = 0.70,
    ) {
        if ($factor <= 0.0 || $factor > 1.0) {
            throw new InvalidArgumentException('El factor de resistencia debe estar entre 0 y 1.');
        }
    }

    public function clave(): string
    {
        return 'resistencia_tipo';
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

        return null;
    }

    public function aplicar(Combatiente $objetivo, Combatiente $actor): ResultadoEfectoItem
    {
        $objetivo->aplicarResistenciaTemporal($this->tipo, $this->factor);

        return new ResultadoEfectoItem(
            texto: "{$objetivo->nombre()} se protegió contra el tipo {$this->tipo->label()}.",
        );
    }
}
