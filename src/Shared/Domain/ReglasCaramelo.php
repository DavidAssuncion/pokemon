<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Reglas del caramelo de tipo (EXP de tipo que otorga cada caramelo).
 *
 * Vive en Shared porque lo consumen el controlador de reclutados y la
 * evolución; la cifra no debe repetirse como literal en cada capa.
 */
final class ReglasCaramelo
{
    public const EXP_POR_CARAMELO = 100;

    /**
     * Factor aplicado a la EXP de tipo exigida para evolucionar. Con 0,5 la
     * evolución cuesta la mitad de caramelos de tipo.
     */
    public const FACTOR_COSTE_EVOLUCION = 0.5;
}
