<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Evolution;

use Src\Shared\Domain\Collection;

/**
 * Colección tipada de miembros de una familia evolutiva, ya ordenada por el
 * resolvedor (stage, parent_id, branch_index, species_id).
 */
final class FamiliaEvolutiva extends Collection
{
    public string $type = MiembroFamiliaEvolutiva::class;

    /**
     * @param  list<MiembroFamiliaEvolutiva>  $miembros
     */
    public function __construct(array $miembros = [])
    {
        parent::__construct($miembros);
    }

    /**
     * @return list<MiembroFamiliaEvolutiva>
     */
    public function miembros(): array
    {
        /** @var list<MiembroFamiliaEvolutiva> $miembros */
        $miembros = $this->items;

        return $miembros;
    }

    public function stageDe(int $id): ?int
    {
        return $this->porId($id)?->stage;
    }

    public function porId(int $id): ?MiembroFamiliaEvolutiva
    {
        foreach ($this->items as $miembro) {
            if ($miembro instanceof MiembroFamiliaEvolutiva && $miembro->id === $id) {
                return $miembro;
            }
        }

        return null;
    }
}
