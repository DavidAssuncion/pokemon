<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Stats;

class StatsValue
{
    public function __construct(
        public ?float $hp = 255,
        public ?float $attack = 255,
        public ?float $defense = 255,
        public ?float $spAtk = 255,
        public ?float $spDef = 255,
        public ?float $speed = 255
    ) {
    }

    /**
     * Normaliza un array (JSON de BD o payload) a un StatsValue.
     * Claves canónicas cortas {hp, atk, def, spAtk, spDef, speed} con fallback
     * a las largas {attack, defense}; keys faltantes → 0.
     */
    public static function desdeArray(array $data): self
    {
        return new self(
            hp: (float) ($data['hp'] ?? 0),
            attack: (float) ($data['attack'] ?? $data['atk'] ?? 0),
            defense: (float) ($data['defense'] ?? $data['def'] ?? 0),
            spAtk: (float) ($data['spAtk'] ?? 0),
            spDef: (float) ($data['spDef'] ?? 0),
            speed: (float) ($data['speed'] ?? 0),
        );
    }

    /**
     * Serialización canónica con claves cortas (contrato frontend + DB JSON).
     *
     * @return array{hp: int, atk: int, def: int, spAtk: int, spDef: int, speed: int}
     */
    public function toArray(): array
    {
        return [
            'hp' => (int) ($this->hp ?? 0),
            'atk' => (int) ($this->attack ?? 0),
            'def' => (int) ($this->defense ?? 0),
            'spAtk' => (int) ($this->spAtk ?? 0),
            'spDef' => (int) ($this->spDef ?? 0),
            'speed' => (int) ($this->speed ?? 0),
        ];
    }

    /**
     * Devuelve una instancia nueva con un stat modificado (inmutabilidad).
     * Acepta clave corta (frontend dar-evs) o propiedad larga.
     */
    public function conStat(string $stat, float $valor): self
    {
        return match ($stat) {
            'hp' => new self(hp: $valor, attack: $this->attack, defense: $this->defense, spAtk: $this->spAtk, spDef: $this->spDef, speed: $this->speed),
            'atk', 'attack' => new self(hp: $this->hp, attack: $valor, defense: $this->defense, spAtk: $this->spAtk, spDef: $this->spDef, speed: $this->speed),
            'def', 'defense' => new self(hp: $this->hp, attack: $this->attack, defense: $valor, spAtk: $this->spAtk, spDef: $this->spDef, speed: $this->speed),
            'spAtk' => new self(hp: $this->hp, attack: $this->attack, defense: $this->defense, spAtk: $valor, spDef: $this->spDef, speed: $this->speed),
            'spDef' => new self(hp: $this->hp, attack: $this->attack, defense: $this->defense, spAtk: $this->spAtk, spDef: $valor, speed: $this->speed),
            'speed' => new self(hp: $this->hp, attack: $this->attack, defense: $this->defense, spAtk: $this->spAtk, spDef: $this->spDef, speed: $valor),
            default => $this,
        };
    }
}
