<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

/**
 * Modo de una expedición de exploración.
 *
 * `MIXTO` representa el comportamiento histórico y solo se usa como valor de
 * compatibilidad para expediciones antiguas o para la API; la interfaz de
 * usuario únicamente ofrece los modos EXPERIENCIA, RECLUTAMIENTO y BOTIN.
 */
enum ModoExploracion: string
{
    case EXPERIENCIA = 'experiencia';
    case RECLUTAMIENTO = 'reclutamiento';
    case BOTIN = 'botin';
    case MIXTO = 'mixto';

    /**
     * Normaliza un valor de entrada. `null` o un valor desconocido se
     * interpretan como MIXTO (comportamiento legado).
     */
    public static function desde(?string $valor): self
    {
        return self::tryFrom($valor ?? '') ?? self::MIXTO;
    }
}
