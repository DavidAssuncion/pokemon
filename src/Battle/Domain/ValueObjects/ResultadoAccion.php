<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

/**
 * Value Object inmutable con el veredicto de actuación de un Combatiente.
 *
 * Sustituye el array {canAct, reason, selfDamage} que devolvía
 * Combatiente::puedeActuar() (deuda F5b). Paridad exacta de comportamiento:
 * el motivo conserva los mensajes y el auto-daño solo aparece en confusión.
 */
final class ResultadoAccion
{
    private function __construct(
        private readonly bool $permitida,
        private readonly string $motivo,
        private readonly float $autoDanio = 0.0,
    ) {
    }

    /**
     * Puede actuar sin motivo (estado sano o inactivo).
     */
    public static function permitida(): self
    {
        return new self(true, '');
    }

    /**
     * Puede actuar pero con motivo informativo (`despertó`, `se descongeló`,
     * `salió de confusión`).
     */
    public static function permitidaConMotivo(string $motivo): self
    {
        return new self(true, $motivo);
    }

    /**
     * No puede actuar (sueño, hielo, parálisis): motivo del bloqueo.
     */
    public static function denegada(string $motivo): self
    {
        return new self(false, $motivo);
    }

    /**
     * No puede actuar y se lastima a sí mismo (confusión): autoDanio > 0.
     */
    public static function denegadaConAutoDanio(string $motivo, float $autoDanio): self
    {
        return new self(false, $motivo, $autoDanio);
    }

    public function esPermitida(): bool
    {
        return $this->permitida;
    }

    public function motivo(): string
    {
        return $this->motivo;
    }

    public function autoDanio(): float
    {
        return $this->autoDanio;
    }

    /**
     * Frontera — shape exacto del contrato previo {canAct, reason, selfDamage}.
     *
     * @return array{canAct: bool, reason: string, selfDamage: float}
     *
     * @deprecated Usar esPermitida()/motivo()/autoDanio().
     */
    public function toArray(): array
    {
        return [
            'canAct' => $this->permitida,
            'reason' => $this->motivo,
            'selfDamage' => $this->autoDanio,
        ];
    }
}
