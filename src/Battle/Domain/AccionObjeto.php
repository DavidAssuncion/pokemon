<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

/**
 * Acción de uso de un objeto de inventario en combate.
 *
 * Es independiente de `AccionBatalla`: usar un objeto consume la acción de
 * la ronda pero no un movimiento, y puede dirigirse a aliados vivos,
 * aliados debilitados o rivales vivos.
 */
final readonly class AccionObjeto
{
    public function __construct(
        public string $actorId,
        public string $objetivoId,
        public string $clave,
    ) {
    }
}
