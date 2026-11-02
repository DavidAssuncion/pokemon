<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\DataTransferObjects;

/**
 * Resultado de resolver una emboscada. Sustituye el array
 * {resolucion, duration_loss, dificultad, retirada_probable?}.
 */
final readonly class ResolucionEmboscada
{
    public function __construct(
        public string $resolucion,
        public int $durationLoss,
        public int $dificultad,
        public ?bool $retiradaProbable = null,
    ) {
    }

    /**
     * @return array{resolucion: string, duration_loss: int, dificultad: int, retirada_probable?: bool}
     */
    public function toArray(): array
    {
        $resultado = [
            'resolucion' => $this->resolucion,
            'duration_loss' => $this->durationLoss,
            'dificultad' => $this->dificultad,
        ];

        if ($this->retiradaProbable !== null) {
            $resultado['retirada_probable'] = $this->retiradaProbable;
        }

        return $resultado;
    }
}
