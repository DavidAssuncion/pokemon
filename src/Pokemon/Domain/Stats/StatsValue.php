<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Stats;

class StatsValue
{
    public function __construct(
        public ?float $hp = 255,
        public ?float $attack = 255,
        public ?float $defense = 255,
        public ?float $spAtk = 255,
        public ?float $spDef = 255,
        public ?float $speed = 255
    ) {
    }
}
