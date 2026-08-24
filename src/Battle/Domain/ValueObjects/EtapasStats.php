<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

/**
 * Value Object inmutable que encapsula las etapas de estadísticas (-6 a +6).
 *
 * Cada etapa representa un cambio en una estadística concreta,
 * con un rango válido de -6 (mínimo) a +6 (máximo).
 */
class EtapasStats
{
    private const MIN_STAGE = -6;

    private const MAX_STAGE = 6;

    /** @var array<string, int> */
    private array $etapas;

    /**
     * @param  array<string, int>  $etapas
     */
    public function __construct(array $etapas = [])
    {
        foreach ($etapas as $stat => $valor) {
            if (! is_int($valor) || $valor < self::MIN_STAGE || $valor > self::MAX_STAGE) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'La etapa para "%s" debe ser un entero entre %d y %d, got %s',
                        $stat,
                        self::MIN_STAGE,
                        self::MAX_STAGE,
                        is_int($valor) ? (string) $valor : gettype($valor)
                    )
                );
            }
        }
        $this->etapas = $etapas;
    }

    /**
     * Retorna una nueva instancia con el cambio aplicado (inmutable).
     */
    public function aplicarCambio(string $stat, int $cambio): self
    {
        $nuevas = $this->etapas;
        $actual = $nuevas[$stat] ?? 0;
        $nuevas[$stat] = max(self::MIN_STAGE, min(self::MAX_STAGE, $actual + $cambio));

        return new self($nuevas);
    }

    /**
     * Obtiene el valor de etapa para una estadística.
     */
    public function obtener(string $stat): int
    {
        return $this->etapas[$stat] ?? 0;
    }

    /**
     * Retorna todas las etapas como array asociativo.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->etapas;
    }

    /**
     * Retorna las etapas no neutras (distintas de cero).
     *
     * @return array<string, int>
     */
    public function obtenerNoNeutras(): array
    {
        return array_filter($this->etapas, fn (int $v) => $v !== 0);
    }

    /**
     * Multiplicador según la etapa de una estadística:
     *  N > 0: (2+N)/2   (ej: +1 → ×1.5, +2 → ×2, +6 → ×4)
     *  N < 0: 2/(2-N)   (ej: -1 → ×0.66, -2 → ×0.5, -6 → ×0.25)
     */
    public function obtenerMultiplicador(string $stat): float
    {
        $stage = $this->obtener($stat);

        if ($stage >= 0) {
            return (2 + $stage) / 2;
        }

        return 2 / (2 - $stage);
    }
}
