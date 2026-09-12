<?php

declare(strict_types=1);

namespace Src\Equipos\Domain;

class TeamAggregate
{
    /**
     * @param  array<int, \App\Models\TeamMember>  $members
     *         Deuda conocida: siguen siendo modelos Eloquent (Infra); no convertirlos a DTO
     *         hasta que las vistas y el contrato JSON consuman la representación canónica.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $userId,
        public readonly array $members = [],
    ) {
    }
}
