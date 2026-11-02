<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\DataTransferObjects;

use Src\Exploraciones\Domain\Collections\MatchupCollection;
use Src\Shared\Collections\StringCollection;

/**
 * Preview de riesgo de una expedición. Sustituye el array que devolvía
 * CalculadorRiesgo::evaluar(). `toArray()` queda como frontera JSON.
 */
final readonly class EvaluacionRiesgo
{
    public function __construct(
        public int $peligroEstrellas,
        public int $afinidad,
        public StringCollection $advertencias,
        public StringCollection $roles,
        public MatchupCollection $matchups,
        public string $riesgo,
        public string $recompensaEsperada,
    ) {
    }

    /**
     * @return array{
     *     peligro_estrellas: int,
     *     afinidad: int,
     *     advertencias: list<string>,
     *     roles: list<string>,
     *     matchups: list<array<string, mixed>>,
     *     riesgo: string,
     *     recompensa_esperada: string,
     * }
     */
    public function toArray(): array
    {
        return [
            'peligro_estrellas' => $this->peligroEstrellas,
            'afinidad' => $this->afinidad,
            'advertencias' => $this->advertencias->toList(),
            'roles' => $this->roles->toList(),
            'matchups' => $this->matchups->toArray(),
            'riesgo' => $this->riesgo,
            'recompensa_esperada' => $this->recompensaEsperada,
        ];
    }
}
