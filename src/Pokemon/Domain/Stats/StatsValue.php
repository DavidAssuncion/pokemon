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
     *
     * @param  array<string, mixed>  $data
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

    /** @var array<string, string> Mapa clave frontend/BD → nombre de propiedad del VO. */
    private const PROPIEDADES = [
        'hp' => 'hp',
        'atk' => 'attack',
        'attack' => 'attack',
        'def' => 'defense',
        'defense' => 'defense',
        'spAtk' => 'spAtk',
        'spDef' => 'spDef',
        'speed' => 'speed',
    ];

    /**
     * Resuelve la propiedad interna desde una clave corta (frontend) o larga (propiedad).
     *
     * @return 'hp'|'attack'|'defense'|'spAtk'|'spDef'|'speed'
     */
    private static function propiedadDe(string $stat): string
    {
        return self::PROPIEDADES[$stat] ?? throw new \InvalidArgumentException("Stat inválido: {$stat}");
    }

    /**
     * Valor numérico del stat (float; sin valor → 0.0).
     */
    public function valorDe(string $stat): float
    {
        return $this->{self::propiedadDe($stat)} ?? 0.0;
    }

    /**
     * Devuelve una instancia nueva con un stat modificado (inmutabilidad).
     * Acepta clave corta (frontend dar-evs) o propiedad larga.
     */
    public function conStat(string $stat, float $valor): self
    {
        $nuevos = [
            'hp' => $this->hp,
            'attack' => $this->attack,
            'defense' => $this->defense,
            'spAtk' => $this->spAtk,
            'spDef' => $this->spDef,
            'speed' => $this->speed,
        ];
        $nuevos[self::propiedadDe($stat)] = $valor;

        return new self(...$nuevos);
    }
}
