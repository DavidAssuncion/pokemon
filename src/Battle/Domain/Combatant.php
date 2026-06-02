<?php

namespace Src\Battle\Domain;

use Src\Pokemon\Domain\PokemonEntity;

class Combatant
{
    public float $currentHp;
    public float $currentDefenseHp;
    public float $currentSpDefenseHp;
    public float $accumulatedSpeed = 0;
    public int $timesActedThisRound = 0;
    public string $id = '';
    public string $nombre = '';
    public string $iconName = '';
    public bool $shiny = false;

    public function __construct(
        public readonly PokemonEntity $pokemon,
        public Position $position,
    ) {
        $this->currentHp = $pokemon->battleStats->hp;
        $this->currentDefenseHp = $pokemon->battleStats->defenseHp;
        $this->currentSpDefenseHp = $pokemon->battleStats->spDefenseHp;
    }

    public function toViewArray(int $teamIdx): array
    {
        $iconName = $this->iconName ?: strtolower($this->nombre);
        $icon = $this->shiny ? "/iconos/shiny/{$iconName}.png" : "/iconos/{$iconName}.png";

        return [
            'refId' => $this->id,
            'nombre' => $this->nombre,
            'icon' => $icon,
            'hp' => $this->currentHp,
            'maxHp' => $this->pokemon->battleStats->hp,
            'defHp' => $this->currentDefenseHp,
            'maxDefHp' => $this->pokemon->battleStats->defenseHp,
            'spDefHp' => $this->currentSpDefenseHp,
            'maxSpDefHp' => $this->pokemon->battleStats->spDefenseHp,
            'posicion' => $this->position->value,
            'alive' => $this->isAlive(),
            'speed' => $this->pokemon->battleStats->speed,
            'accumulatedSpeed' => $this->accumulatedSpeed,
            'team' => $teamIdx,
        ];
    }

    public function isAlive(): bool
    {
        return $this->currentHp > 0;
    }

    public function resetAccumulatedSpeed(): void
    {
        $this->accumulatedSpeed = 0;
    }

    public function addSpeed(): void
    {
        $this->accumulatedSpeed += $this->pokemon->battleStats->speed;
    }

    public function reducirSpeed(float $amount): void
    {
        $this->accumulatedSpeed -= $amount;
    }

    public function recibirDaño(float $daño, bool $isSpecial): float
    {
        $barrera = $isSpecial ? $this->currentSpDefenseHp : $this->currentDefenseHp;

        $dañoBarrera = min($barrera, $daño);

        if ($isSpecial) {
            $this->currentSpDefenseHp -= $dañoBarrera;
        } else {
            $this->currentDefenseHp -= $dañoBarrera;
        }

        $excedente = $daño - $dañoBarrera;

        if ($excedente > 0) {
            $this->currentHp -= $excedente;
            if ($this->currentHp < 0) {
                $this->currentHp = 0;
            }
        }

        return $daño;
    }

    public function curarHp(float $porcentaje): void
    {
        $this->currentHp = min(
            $this->pokemon->battleStats->hp,
            $this->currentHp + $this->pokemon->battleStats->hp * $porcentaje / 100
        );
    }

    public function curarBarreras(float $porcentaje): void
    {
        $this->currentDefenseHp = min(
            $this->pokemon->battleStats->defenseHp,
            $this->currentDefenseHp + $this->pokemon->battleStats->defenseHp * $porcentaje / 100
        );
        $this->currentSpDefenseHp = min(
            $this->pokemon->battleStats->spDefenseHp,
            $this->currentSpDefenseHp + $this->pokemon->battleStats->spDefenseHp * $porcentaje / 100
        );
    }

    public function puedeAtacarRetaguardia(): bool
    {
        return $this->position === Position::RETAGUARDIA;
    }

    public function estaEnVanguardia(): bool
    {
        return $this->position === Position::VANGUARDIA;
    }

    public function estaEnRetaguardia(): bool
    {
        return $this->position === Position::RETAGUARDIA;
    }
}
