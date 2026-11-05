<?php

declare(strict_types=1);

namespace Src\Battle\Domain\UsoObjetos\Efectos;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ObjetivosItem;
use Src\Battle\Domain\UsoObjetos\EfectoItem;
use Src\Battle\Domain\UsoObjetos\ResultadoEfectoItem;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/**
 * Aplica/refresca la condición de crítico mejorado durante cinco turnos.
 */
final readonly class EfectoMejorarCritico implements EfectoItem
{
    public function __construct(
        private int $turnos = 5,
    ) {
    }

    public function clave(): string
    {
        return 'mejorar_critico';
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
        $objetivo->reemplazarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::CRITICO_MEJORADO,
            turnos: max(1, $this->turnos),
            origenId: $actor->id(),
        ));

        return new ResultadoEfectoItem(
            texto: "{$objetivo->nombre()} mejoró su probabilidad de crítico.",
        );
    }
}
