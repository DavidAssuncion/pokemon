<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

interface ModificaPrecisionEstado
{
    public function multiplicadorPrecisionEstado(): float;
}
