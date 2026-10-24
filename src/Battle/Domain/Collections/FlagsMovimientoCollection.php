<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Collections;

use Src\Battle\Domain\Enums\FlagMovimiento;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de flags de movimiento.
 */
final class FlagsMovimientoCollection extends Collection
{
    public string $type = FlagMovimiento::class;

    /**
     * @param  list<string>  $flags
     */
    public static function desdeArray(array $flags): self
    {
        $items = [];
        foreach ($flags as $flag) {
            $item = FlagMovimiento::tryFrom($flag);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return new self($items);
    }

    public function tiene(FlagMovimiento $flag): bool
    {
        foreach ($this as $item) {
            if ($item === $flag) {
                return true;
            }
        }

        return false;
    }

    public function esContacto(): bool
    {
        return $this->tiene(FlagMovimiento::CONTACTO);
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        return $this->pluck(static fn (FlagMovimiento $flag): string => $flag->value);
    }
}
