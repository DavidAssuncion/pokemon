<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

/**
 * Habilidad que impide recibir golpes críticos (Predicción).
 */
interface InmuneCritico
{
    public function esInmuneACritico(): bool;
}
