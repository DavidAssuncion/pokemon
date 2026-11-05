<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

/**
 * Perfil de recompensas y eventos asociado a un modo de exploración.
 *
 * Agrupa los pesos de evento y los multiplicadores que la aplicación aplicará
 * sobre EXP, caramelos, captura y botín. El perfil no ejecuta cálculos: solo
 * transporta la configuración de balance del modo.
 */
final readonly class PerfilExploracion
{
    public function __construct(
        public ModoExploracion $modo,
        public PesosEvento $pesos,
        public float $multiplicadorExp,
        public float $multiplicadorCaramelos,
        public float $factorCaptura,
        public float $multiplicadorBotin,
    ) {
    }

    /**
     * Perfil predeterminado de cada modo.
     */
    public static function de(ModoExploracion $modo): self
    {
        return match ($modo) {
            ModoExploracion::MIXTO => new self(
                modo: $modo,
                pesos: PesosEvento::para($modo),
                multiplicadorExp: 1.00,
                multiplicadorCaramelos: 1.00,
                factorCaptura: 1.00,
                multiplicadorBotin: 1.00,
            ),
            ModoExploracion::EXPERIENCIA => new self(
                modo: $modo,
                pesos: PesosEvento::para($modo),
                multiplicadorExp: 1.25,
                multiplicadorCaramelos: 0.75,
                factorCaptura: 0.50,
                multiplicadorBotin: 0.50,
            ),
            ModoExploracion::RECLUTAMIENTO => new self(
                modo: $modo,
                pesos: PesosEvento::para($modo),
                multiplicadorExp: 0.75,
                multiplicadorCaramelos: 0.75,
                factorCaptura: 2.00,
                multiplicadorBotin: 0.50,
            ),
            ModoExploracion::BOTIN => new self(
                modo: $modo,
                pesos: PesosEvento::para($modo),
                multiplicadorExp: 0.50,
                multiplicadorCaramelos: 0.50,
                factorCaptura: 0.50,
                multiplicadorBotin: 1.50,
            ),
        };
    }
}
