<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain;

use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ValueObjects\ColeccionMovimientos;
use Src\Pokemon\Domain\Stats\BattleStats;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Tipos\TiposCollection;

class PokemonEntity
{
    private BattleStats $battleStats;

    private StatsValue $stats;

    private StatsValue $evs;

    private ColeccionMovimientos $moves;

    private TiposCollection $tiposCollection;

    private ?string $objetos = null;

    private ?string $habilidad = null;

    /**
     * @param  MovimientoBatalla[]  $moves
     */
    public function __construct(
        StatsValue $stats,
        StatsValue $evs,
        array $moves,
        TiposCollection $tiposCollection,
        ?string $objetos = null,
        ?string $habilidad = null,
        ?BattleStats $precomputedBattleStats = null,
    ) {
        $this->stats = $stats;
        $this->evs = $evs;
        $this->moves = new ColeccionMovimientos($moves);
        $this->tiposCollection = $tiposCollection;
        $this->objetos = $objetos;
        $this->habilidad = $habilidad;
        $this->battleStats = $precomputedBattleStats ?? new BattleStats($stats, $evs);
    }

    public function battleStats(): BattleStats
    {
        return $this->battleStats;
    }

    public function stats(): StatsValue
    {
        return $this->stats;
    }

    public function evs(): StatsValue
    {
        return $this->evs;
    }

    public function moves(): ColeccionMovimientos
    {
        return $this->moves;
    }

    public function tiposCollection(): TiposCollection
    {
        return $this->tiposCollection;
    }

    public function objetos(): ?string
    {
        return $this->objetos;
    }

    public function habilidad(): ?string
    {
        return $this->habilidad;
    }
}
