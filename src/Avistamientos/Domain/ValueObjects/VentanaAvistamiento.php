<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Ventana temporal de un avistamiento: tres días naturales locales desde su
 * inicio, identificados por un índice consecutivo.
 */
final class VentanaAvistamiento
{
    public function __construct(
        public readonly int $indice,
        public readonly DateTimeImmutable $inicio,
        public readonly DateTimeImmutable $fin,
    ) {
    }

    /**
     * ¿El instante dado está dentro de la ventana? El fin es exclusivo.
     */
    public function contiene(DateTimeImmutable $instante): bool
    {
        return $instante >= $this->inicio && $instante < $this->fin;
    }

    /**
     * Segundos que faltan hasta el fin de la ventana; nunca negativo.
     */
    public function segundosRestantes(DateTimeImmutable $instante): int
    {
        return max(0, $this->fin->getTimestamp() - $instante->getTimestamp());
    }

    /**
     * Frontera — representación serializable de la ventana.
     *
     * @return array{indice: int, inicio: string, fin: string}
     */
    public function aArray(): array
    {
        return [
            'indice' => $this->indice,
            'inicio' => $this->inicio->format(DateTimeInterface::ATOM),
            'fin' => $this->fin->format(DateTimeInterface::ATOM),
        ];
    }
}
