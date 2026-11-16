<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Pc;

/**
 * Origen del PC rival calculado, expuesto para que el frontend ajuste el copy
 * (ruta se muestra como estimación; entrenador y gimnasio son deterministas).
 */
enum MetodoPcRival: string
{
    case PROMEDIO_POOL = 'promedio_pool';
    case RIVAL_FIJADO = 'rival_fijado';
    case GENERADOR_DETERMINISTA = 'generador_determinista';
    case CATALOGO_ETAPA = 'catalogo_etapa';
}
