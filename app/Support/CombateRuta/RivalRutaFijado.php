<?php

declare(strict_types=1);

namespace App\Support\CombateRuta;

use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;

/**
 * Snapshot del rival de ruta fijado al abrir el modal de configuración.
 *
 * Vive en sesión bajo un `preview_id` aleatorio; no se persiste en base de datos.
 */
final readonly class RivalRutaFijado
{
    public function __construct(
        public string $previewId,
        public int $habitatId,
        public int $nivel,
        public int $nivelJugador,
        public int $creadoEn,
        public DatosPokemonBatallaCollection $equipo,
    ) {
    }
}
