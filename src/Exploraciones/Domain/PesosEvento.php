<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use InvalidArgumentException;

/**
 * Pesos porcentuales de los tipos de evento de una expedición.
 *
 * Los cinco pesos deben sumar exactamente 100. Es un objeto inmutable: cada
 * modo construye su propia instancia mediante `para()`.
 */
final readonly class PesosEvento
{
    private const TOTAL = 100;

    public function __construct(
        public int $encuentro,
        public int $hallazgo,
        public int $especial,
        public int $contratiempo,
        public int $neutral,
    ) {
        foreach ([
            'encuentro' => $this->encuentro,
            'hallazgo' => $this->hallazgo,
            'especial' => $this->especial,
            'contratiempo' => $this->contratiempo,
            'neutral' => $this->neutral,
        ] as $nombre => $peso) {
            if ($peso < 0) {
                throw new InvalidArgumentException("El peso de {$nombre} no puede ser negativo.");
            }
        }

        if ($this->total() !== self::TOTAL) {
            throw new InvalidArgumentException(sprintf(
                'Los pesos de evento deben sumar %d; se recibió %d.',
                self::TOTAL,
                $this->total(),
            ));
        }
    }

    /**
     * Pesos predeterminados de cada modo de exploración.
     */
    public static function para(ModoExploracion $modo): self
    {
        return match ($modo) {
            ModoExploracion::MIXTO => new self(45, 20, 15, 10, 10),
            ModoExploracion::EXPERIENCIA => new self(65, 5, 15, 10, 5),
            ModoExploracion::RECLUTAMIENTO => new self(55, 10, 15, 10, 10),
            ModoExploracion::BOTIN => new self(20, 45, 10, 15, 10),
        };
    }

    public function total(): int
    {
        return $this->encuentro + $this->hallazgo + $this->especial + $this->contratiempo + $this->neutral;
    }
}
