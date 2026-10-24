<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Modos de interacción con el objeto equipado del objetivo.
 *
 * El catálogo activa uno de estos modos mediante `data.modo_objeto` y el
 * ejecutor genérico `EjecutorInteraccionObjeto` aplica la regla. Así no hace
 * falta una clase PHP por cada movimiento de robo/trueque/embargo.
 */
enum ModoInteraccionObjeto: string
{
    case DESTRUIR = 'destruir';
    case ROBAR = 'robar';
    case INTERCAMBIAR = 'intercambiar';
    case BLOQUEAR = 'bloquear';
    case BLOQUEAR_TODOS = 'bloquear_todos';
    case RECICLAR = 'reciclar';

    public function label(): string
    {
        return match ($this) {
            self::DESTRUIR => 'destruir el objeto',
            self::ROBAR => 'robar el objeto',
            self::INTERCAMBIAR => 'intercambiar objetos',
            self::BLOQUEAR => 'bloquear el objeto',
            self::BLOQUEAR_TODOS => 'bloquear todos los objetos',
            self::RECICLAR => 'reciclar el objeto',
        };
    }
}
