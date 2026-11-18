<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Stats;

class BattleStats
{
    public const IV = 5;
    public const EV = 4;

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

    private float $multiplicadorStats = 1.0;

    /**
     * @param  float  $multiplicadorStats  Efecto permanente sobre las estadísticas de combate
     *                                     (no es una etapa). Se aplica tras la fórmula de nivel a
     *                                     ataque, defensa, ataque especial, defensa especial y
     *                                     velocidad, y a las barreras `defenseHp`/`spDefenseHp`.
     *                                     La vida (HP) queda excluida.
     */
    public function __construct(
        StatsValue $stats,
        StatsValue $evs,
        int $nivel = 100,
        ?StatsValue $ivs = null,
        float $multiplicadorStats = 1.0,
    ) {
        $this->nivel = $nivel;
        $ivs ??= StatsValue::ceros();
        $this->ivs = $ivs;
        $this->multiplicadorStats = $multiplicadorStats;
        $this->calcularStats($stats, $evs, $nivel, $ivs);
    }

    public function nivel(): int
    {
        return $this->nivel;
    }

    public function ivs(): StatsValue
    {
        return $this->ivs ?? StatsValue::ceros();
    }

    /**
     * Multiplicador permanente de las estadísticas de combate. No es una etapa
     * y no afecta a la vida.
     */
    public function multiplicadorStats(): float
    {
        return $this->multiplicadorStats;
    }

    private function calcularStats(StatsValue $stats, StatsValue $evs, int $nivel, StatsValue $ivs): void
    {
        $hpIv = $ivs->hp ?? 0.0;
        $atkIv = $ivs->attack ?? 0.0;
        $defIv = $ivs->defense ?? 0.0;
        $spAtkIv = $ivs->spAtk ?? 0.0;
        $spDefIv = $ivs->spDef ?? 0.0;
        $speedIv = $ivs->speed ?? 0.0;

        $escala = max(0.0, $this->multiplicadorStats);

        $this->hp = $this->calcularHp($stats->hp, $evs->hp, $nivel, $hpIv);
        $this->defenseHp = $this->calcularHp($stats->defense, $evs->defense, $nivel, $defIv) * $escala;
        $this->spDefenseHp = $this->calcularHp($stats->spDef, $evs->spDef, $nivel, $spDefIv) * $escala;
        $this->attack = $this->calcularStat($stats->attack, $evs->attack, $nivel, $atkIv) * $escala;
        $this->defense = $this->calcularStat($stats->defense, $evs->defense, $nivel, $defIv) * $escala;
        $this->spAtk = $this->calcularStat($stats->spAtk, $evs->spAtk, $nivel, $spAtkIv) * $escala;
        $this->spDef = $this->calcularStat($stats->spDef, $evs->spDef, $nivel, $spDefIv) * $escala;
        $this->speed = $this->calcularStat($stats->speed, $evs->speed, $nivel, $speedIv) * $escala;
    }

    private function calcularHp(float $base, float $evs, int $nivel, float $iv = 0.0): float
    {
        return floor(((2 * $base + ($iv * self::IV) + floor($evs / self::EV)) * $nivel / 100) + $nivel + 10);
    }

    private function calcularStat(float $base, float $evs, int $nivel, float $iv = 0.0): float
    {
        return floor(((2 * $base + ($iv * self::IV) + floor($evs / self::EV)) * $nivel / 100) + 5);
    }
}
