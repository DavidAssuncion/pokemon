<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Objetivos válidos de un objeto usado en combate.
 *
 * `ALIADO_VIVO` incluye al propio actor: no se necesita un caso separado
 * para "objetivo propio".
 */
enum ObjetivosItem: string
{
    case ALIADO_VIVO = 'aliado_vivo';
    case ALIADO_DEBILITADO = 'aliado_debilitado';
    case RIVAL_VIVO = 'rival_vivo';

    public function label(): string
    {
        return match ($this) {
            self::ALIADO_VIVO => 'un aliado vivo',
            self::ALIADO_DEBILITADO => 'un aliado debilitado',
            self::RIVAL_VIVO => 'un rival vivo',
        };
    }
}
