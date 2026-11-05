<?php

declare(strict_types=1);

namespace Src\Habitats\Domain\DataTransferObjects;

/**
 * Miembro resuelto de una cadena evolutiva con su etapa (1-3).
 *
 * Sustituye el array {id, name, icon, stage, species_id, parent_id} que
 * producía ResolvedorCadenasEvolutivas::getFamilyMembersByChain().
 */
final readonly class MiembroCadena
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly string $icono,
        public readonly int $stage,
        public readonly int $speciesId,
        public readonly ?int $parentId = null,
    ) {
    }

    /**
     * Frontera — shape aditivo respecto al contrato previo.
     *
     * @return array{id: int, name: string, icon: string, stage: int, species_id: int, parent_id: int|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->nombre,
            'icon' => $this->icono,
            'stage' => $this->stage,
            'species_id' => $this->speciesId,
            'parent_id' => $this->parentId,
        ];
    }
}
