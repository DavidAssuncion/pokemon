<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\DataTransferObjects;

/**
 * Resultado de resolver un encuentro (normal/grupo/excepcional/emboscada).
 * Sustituye el array {resolucion, duration_loss, retirada?, reason?}.
 */
final readonly class ResolucionEncuentro
{
    public function __construct(
        public string $resolucion,
        public int $durationLoss,
        public ?bool $retirada = null,
        public ?string $reason = null,
    ) {
    }

    /**
     * @return array{resolucion: string, duration_loss: int, retirada?: bool, reason?: string}
     */
    public function toArray(): array
    {
        $resultado = [
            'resolucion' => $this->resolucion,
            'duration_loss' => $this->durationLoss,
        ];

        if ($this->retirada !== null) {
            $resultado['retirada'] = $this->retirada;
        }

        if ($this->reason !== null) {
            $resultado['reason'] = $this->reason;
        }

        return $resultado;
    }
}
