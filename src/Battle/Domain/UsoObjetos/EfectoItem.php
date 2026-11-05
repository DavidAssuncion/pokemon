<?php

declare(strict_types=1);

namespace Src\Battle\Domain\UsoObjetos;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ObjetivosItem;

/**
 * Efecto de un objeto de inventario usado manualmente en combate.
 *
 * El mismo contrato sirve para curar, revivir, mejorar estadísticas o
 * aplicar resistencias temporales. `validar()` devuelve un motivo legible
 * (null si el efecto es aplicable) y `aplicar()` muta al objetivo y
 * devuelve el texto para el log.
 */
interface EfectoItem
{
    public function clave(): string;

    public function objetivos(): ObjetivosItem;

    /**
     * Devuelve el motivo por el que el efecto no puede aplicarse, o null
     * si es aplicable.
     */
    public function validar(Combatiente $objetivo): ?string;

    public function aplicar(Combatiente $objetivo, Combatiente $actor): ResultadoEfectoItem;
}
