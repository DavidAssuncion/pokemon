<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

/**
 * Parte/localización de un jefe con HP propio. Al romperse deja al jefe
 * vulnerable durante unos turnos (se aplica desde Combatiente).
 */
class ParteJefe
{
    public function __construct(
        public string $id,
        public string $nombre,
        public float $hp,
        public float $hpMax,
        public bool $roto = false,
    ) {
    }

    public function estaRota(): bool
    {
        return $this->roto || $this->hp <= 0.0;
    }

    public function recibirDanio(float $dano): bool
    {
        if ($this->estaRota() || $dano <= 0.0) {
            return false;
        }

        $this->hp = max(0.0, $this->hp - $dano);
        if ($this->hp <= 0.0) {
            $this->roto = true;

            return true;
        }

        return false;
    }

    /**
     * @return array{id: string, nombre: string, hp: float, hpMax: float, roto: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'hp' => $this->hp,
            'hpMax' => $this->hpMax,
            'roto' => $this->estaRota(),
        ];
    }
}
