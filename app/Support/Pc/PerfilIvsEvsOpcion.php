<?php

declare(strict_types=1);

namespace App\Support\Pc;

/**
 * Opción de perfil IV/EV mostrada en la leyenda.
 *
 * Los valores de IV/EV provienen de `PerfilIvsEvs`; el DTO no los calcula ni
 * los duplica.
 */
final readonly class PerfilIvsEvsOpcion
{
    public function __construct(
        public string $clave,
        public string $etiqueta,
        public string $descripcion,
        public int $statsAlMaximo,
        public int $ivMaximo,
        public int $ivResto,
        public int $evMaximo,
        public int $evResto,
        public bool $actual,
    ) {
    }

    /**
     * @return array{
     *     clave: string,
     *     etiqueta: string,
     *     descripcion: string,
     *     stats_al_maximo: int,
     *     iv_maximo: int,
     *     iv_resto: int,
     *     ev_maximo: int,
     *     ev_resto: int,
     *     actual: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'clave' => $this->clave,
            'etiqueta' => $this->etiqueta,
            'descripcion' => $this->descripcion,
            'stats_al_maximo' => $this->statsAlMaximo,
            'iv_maximo' => $this->ivMaximo,
            'iv_resto' => $this->ivResto,
            'ev_maximo' => $this->evMaximo,
            'ev_resto' => $this->evResto,
            'actual' => $this->actual,
        ];
    }
}
