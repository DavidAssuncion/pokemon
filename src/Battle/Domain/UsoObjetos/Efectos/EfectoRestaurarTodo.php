<?php

declare(strict_types=1);

namespace Src\Battle\Domain\UsoObjetos\Efectos;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\ObjetivosItem;
use Src\Battle\Domain\UsoObjetos\EfectoItem;
use Src\Battle\Domain\UsoObjetos\ResultadoEfectoItem;

/**
 * Restaura PS, estado, usos de movimiento y multiplicadores temporales.
 */
final readonly class EfectoRestaurarTodo implements EfectoItem
{
    public function clave(): string
    {
        return 'restaurar_todo';
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
        $hpMaximo = max(1.0, $objetivo->pokemon()->battleStats()->hp);
        $curado = $objetivo->curarHpCantidad($hpMaximo - $objetivo->hpActual());

        $objetivo->setEstado(EstadoPokemon::NONE);
        $objetivo->setContadorVenenoGrave(0);
        $objetivo->quitarCondicion(ClaveCondicion::CONFUSION);
        $objetivo->restaurarUsos();
        $objetivo->setMultiplicadores($objetivo->multiplicadores()->reiniciarTemporales());

        return new ResultadoEfectoItem(
            texto: "{$objetivo->nombre()} recuperó todos sus PS, su estado y sus usos.",
            curado: (int) round($curado),
        );
    }
}
