<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\Enums\FlagMovimiento;

interface InmuneAFlag
{
    public function esInmuneAFlag(FlagMovimiento $flag): bool;
}
