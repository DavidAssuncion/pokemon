<?php

declare(strict_types=1);

namespace App\Support\Pc;

/**
 * Stat anónimo de un Pokémon rival: base, IV, EV y valor calculado al nivel
 * del combate.
 */
final readonly class StatPreview
{
    public function __construct(
        public string $clave,
        public string $etiqueta,
        public int $base,
        public int $iv,
        public int $ev,
        public int $calculado,
    ) {
    }

    /**
     * @return array{
     *     clave: string,
     *     etiqueta: string,
     *     base: int,
     *     iv: int,
     *     ev: int,
     *     calculado: int
     * }
     */
    public function toArray(): array
    {
        return [
            'clave' => $this->clave,
            'etiqueta' => $this->etiqueta,
            'base' => $this->base,
            'iv' => $this->iv,
            'ev' => $this->ev,
            'calculado' => $this->calculado,
        ];
    }
}
