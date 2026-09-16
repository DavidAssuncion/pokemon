<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Stats;

class BattleStats
{
    public readonly int $nivel;

    private ?StatsValue $ivs = null;

    public float $hp;

    public float $defenseHp;

    public float $spDefenseHp;

    public float $attack;

    public float $defense;

    public float $spAtk;

    public float $spDef;

    public float $speed;

    public function __construct(
        StatsValue $stats,
        StatsValue $evs,
        int $nivel = 100,
        ?StatsValue $ivs = null,
    ) {
        $this->nivel = $nivel;
        $this->ivs = $ivs;
        $this->calcularStats($stats, $evs, $nivel, $ivs);
    }

    public function nivel(): int
    {
        return $this->nivel;
    }

    public function ivs(): ?StatsValue
    {
        return $this->ivs;
    }

    private function calcularStats(StatsValue $stats, StatsValue $evs, int $nivel, ?StatsValue $ivs = null): void
    {
        $hpIv = $ivs->hp ?? 0.0;
        $atkIv = $ivs->attack ?? 0.0;
        $defIv = $ivs->defense ?? 0.0;
        $spAtkIv = $ivs->spAtk ?? 0.0;
        $spDefIv = $ivs->spDef ?? 0.0;
        $speedIv = $ivs->speed ?? 0.0;

        $this->hp = $this->calcularHp($stats->hp, $evs->hp, $nivel, $hpIv);
        $this->defenseHp = $this->calcularHp($stats->defense, $evs->defense, $nivel, $defIv);
        $this->spDefenseHp = $this->calcularHp($stats->spDef, $evs->spDef, $nivel, $spDefIv);
        $this->attack = $this->calcularStat($stats->attack, $evs->attack, $nivel, $atkIv);
        $this->defense = $this->calcularStat($stats->defense, $evs->defense, $nivel, $defIv);
        $this->spAtk = $this->calcularStat($stats->spAtk, $evs->spAtk, $nivel, $spAtkIv);
        $this->spDef = $this->calcularStat($stats->spDef, $evs->spDef, $nivel, $spDefIv);
        $this->speed = $this->calcularStat($stats->speed, $evs->speed, $nivel, $speedIv);
    }

    private function calcularHp(float $base, float $evs, int $nivel, float $iv = 0.0): float
    {
        return floor(((2 * $base + ($iv * 4) + floor($evs / 4)) * $nivel / 100) + $nivel + 10);
    }

    private function calcularStat(float $base, float $evs, int $nivel, float $iv = 0.0): float
    {
        return floor(((2 * $base + ($iv * 4) + floor($evs / 4)) * $nivel / 100) + 5);
    }
}
