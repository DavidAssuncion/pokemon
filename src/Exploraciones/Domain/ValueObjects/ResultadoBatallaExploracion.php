<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\ValueObjects;

use Src\Battle\Domain\ValueObjects\BattleLog;

/**
 * Value Object inmutable con el resultado de un combate de exploración 1v1.
 *
 * Sustituye el array {victoria, hp_final, ...} que devolvía
 * CombateExploracion::combatirDatos() (deuda F5b). toArray() solo se usa en
 * la frontera (persistencia del contrato de eventos / vista).
 */
final readonly class ResultadoBatallaExploracion
{
    public function __construct(
        public readonly bool $victoria,
        public readonly float $hpFinal,
        public readonly float $barreraFisicaFinal,
        public readonly float $barreraEspecialFinal,
        public readonly float $hpMax,
        public readonly float $barreraFisicaMax,
        public readonly float $barreraEspecialMax,
        public readonly BattleLog $log,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato previo (claves snake_case).
     *
     * @return array{victoria: bool, hp_final: float, barrera_fisica_final: float, barrera_especial_final: float, log: list<string>, hp_max: float, barrera_fisica_max: float, barrera_especial_max: float}
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return [
            'victoria' => $this->victoria,
            'hp_final' => $this->hpFinal,
            'barrera_fisica_final' => $this->barreraFisicaFinal,
            'barrera_especial_final' => $this->barreraEspecialFinal,
            'log' => $this->log->entries(),
            'hp_max' => $this->hpMax,
            'barrera_fisica_max' => $this->barreraFisicaMax,
            'barrera_especial_max' => $this->barreraEspecialMax,
        ];
    }
}
