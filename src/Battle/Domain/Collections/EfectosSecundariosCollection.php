<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Collections;

use Src\Battle\Domain\ValueObjects\EfectoSecundario;
use Src\Shared\Domain\Collection;

/**
 * Colección tipada de efectos secundarios de un movimiento.
 */
final class EfectosSecundariosCollection extends Collection
{
    public string $type = EfectoSecundario::class;

    /**
     * @param  list<array<string, mixed>>  $efectos
     */
    public static function desdeArray(array $efectos, bool $esAuto = false): self
    {
        $items = [];

        foreach ($efectos as $datos) {
            $items[] = EfectoSecundario::desdeArray($datos, $esAuto);

            if (isset($datos['self']) && is_array($datos['self'])) {
                $self = $datos['self'];
                if (! array_key_exists('chance', $self)) {
                    $self['chance'] = $datos['chance'] ?? 100;
                }
                $items[] = EfectoSecundario::desdeArray($self, true);
            }
        }

        return new self($items);
    }

    /**
     * @return list<EfectoSecundario>
     */
    public function all(): array
    {
        return $this->items;
    }
}
