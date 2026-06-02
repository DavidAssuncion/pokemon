<?php

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatant;
use Src\Battle\Domain\Observer\BattleSubject;

class AbilityEffect implements EffectInterface
{
    public function __construct(
        private readonly string $clave,
        private readonly string $habilidadNombre,
        private readonly bool $unico = false,
    ) {}

    public function aplicar(Combatant $portador, BattleSubject $subject): void
    {
        // Será implementado cuando se definan los efectos concretos
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function esUnico(): bool
    {
        return $this->unico;
    }
}
