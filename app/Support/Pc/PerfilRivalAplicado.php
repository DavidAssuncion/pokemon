<?php

declare(strict_types=1);

namespace App\Support\Pc;

/**
 * Perfil de IV/EV aplicado al rival en el combate actual.
 *
 * `nivelStats` es interno: solo sirve para recalcular los stats de combate y
 * nunca se serializa al cliente.
 */
final readonly class PerfilRivalAplicado
{
    public function __construct(
        public string $clave,
        public string $etiqueta,
        public int $nivelStats,
    ) {
    }

    /**
     * @return array{clave: string, etiqueta: string}
     */
    public function toArray(): array
    {
        return [
            'clave' => $this->clave,
            'etiqueta' => $this->etiqueta,
        ];
    }
}
