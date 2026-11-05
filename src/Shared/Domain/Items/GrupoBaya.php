<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Items;

/**
 * Agrupación funcional de las 36 bayas para el catálogo de plantación.
 */
enum GrupoBaya: string
{
    case MEDICINA = 'medicina';
    case MEJORA = 'mejora';
    case REDUCTOR = 'reductor';
    case RESISTENCIA = 'resistencia';

    public function label(): string
    {
        return match ($this) {
            self::MEDICINA => 'Medicinas',
            self::MEJORA => 'Mejoras',
            self::REDUCTOR => 'Reductores',
            self::RESISTENCIA => 'Resistencias',
        };
    }

    public function orden(): int
    {
        return match ($this) {
            self::MEDICINA => 1,
            self::MEJORA => 2,
            self::REDUCTOR => 3,
            self::RESISTENCIA => 4,
        };
    }
}
