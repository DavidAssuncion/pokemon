<?php

declare(strict_types=1);

namespace Tests\Support\Battle;

use Src\Battle\Domain\ValueObjects\BattleLog;

/**
 * Resultado de una ejecución del arnés.
 */
final readonly class ResultadoBatallaHarness
{
    /**
     * @param  array<string, float>  $hpFinales
     * @param  list<ViolacionInvariante>  $violacionesSeguras
     * @param  list<ViolacionInvariante>  $violacionesDiagnosticas
     */
    public function __construct(
        public string $escenario,
        public int $semilla,
        public BattleLog $log,
        public int $rondas,
        public int $maxRondas,
        public bool $estancada,
        public array $hpFinales,
        public array $violacionesSeguras,
        public array $violacionesDiagnosticas,
    ) {
    }

    public function tieneViolacionesSeguras(): bool
    {
        return $this->violacionesSeguras !== [];
    }

    public function textoLog(): string
    {
        return implode("\n", $this->log->entries()->toList());
    }

    public function hpDe(string $combatienteId): ?float
    {
        return $this->hpFinales[$combatienteId] ?? null;
    }
}
