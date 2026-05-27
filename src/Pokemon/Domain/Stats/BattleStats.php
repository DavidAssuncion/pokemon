<?php

namespace Src\Pokemon\Domain\Stats;

class BattleStats
{
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
        int $nivel = 100
    ) {
        $this->calcularStats($stats, $evs, $nivel);
    }

    private function calcularStats(StatsValue $stats, StatsValue $evs, int $nivel): void
    {
        $this->hp = $this->calcularHp($stats->hp, $evs->hp, $nivel);
        $this->defenseHp = $this->calcularHp($stats->defense, $evs->defense, $nivel);
        $this->spDefenseHp = $this->calcularHp($stats->spDef, $evs->spDef, $nivel);
        $this->attack = $this->calcularStat($stats->attack, $evs->attack, $nivel);
        $this->defense = $this->calcularStat($stats->defense, $evs->defense, $nivel);
        $this->spAtk = $this->calcularStat($stats->spAtk, $evs->spAtk, $nivel);
        $this->spDef = $this->calcularStat($stats->spDef, $evs->spDef, $nivel);
        $this->speed = $this->calcularStat($stats->speed, $evs->speed, $nivel);
    }

    private function calcularHp(float $base, float $evs, $nivel): float
    {
        return floor(((2 * $base + floor($evs / 4)) * $nivel / 100) + $nivel + 10);
    }

    private function calcularStat(float $base, float $evs, $nivel): float
    {
        return floor(((2 * $base + floor($evs / 4)) * $nivel / 100) + 5);
    }
}
