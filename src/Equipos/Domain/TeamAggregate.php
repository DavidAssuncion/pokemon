<?php

declare(strict_types=1);

namespace Src\Equipos\Domain;

class TeamAggregate
{
    /** @param array $members Array of member data (can be Eloquent models from Infra) */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $members = [],
    ) {
    }
}
