<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Sendas;

/**
 * Beneficios pasivos que aporta una Senda de tipo.
 *
 * Los porcentajes son absolutos, no compuestos: N5 vale 0,20 y no 1,21.
 * `danoRecibido` representa la reducción del daño entrante.
 */
final readonly class BeneficiosSenda
{
    public function __construct(
        public float $captura,
        public float $danoInfligido,
        public float $danoRecibido,
        public float $carameloTipo,
    ) {
    }

    public static function sinBonus(): self
    {
        return new self(0.0, 0.0, 0.0, 0.0);
    }

    /**
     * @param  array<int, mixed>  $porcentajes  nivel => porcentaje absoluto (0..1)
     */
    public static function para(NivelSenda $nivel, array $porcentajes = []): self
    {
        if (! $nivel->tienePasivos()) {
            return self::sinBonus();
        }

        $defecto = $nivel === NivelSenda::N5 ? 0.20 : 0.10;
        $valor = $porcentajes[$nivel->value] ?? $defecto;

        if (! is_numeric($valor) || $valor < 0 || $valor > 1) {
            $valor = $defecto;
        }

        $valor = (float) $valor;

        return new self($valor, $valor, $valor, $valor);
    }
}
