<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Pokemon\Domain\Stats\GeneradorIvs;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Domain\Sendas\MapaSendaJugador;
use Src\Shared\Tipos\TipoPokemon;

class DatosPokemonBatalla
{
    public readonly StatsValue $evs;

    public readonly StatsValue $ivs;

    /**
     * @param  MovimientoBatalla[]  $moves
     * @param  TipoPokemon[]  $tipos
     * @param  string[]  $effectKeys  Claves de efectos/habilidades (ej: 'armor_pierce')
     * @param  StatsValue|null  $evs  Si no se especifican, se inicializan a 0.
     * @param  StatsValue|null  $ivs  Si no se especifican, se generan aleatorios (0-15).
     */
    public function __construct(
        public readonly string $id,
        public readonly string $nombre,
        public readonly int $hp,
        public readonly int $atk,
        public readonly int $def,
        public readonly int $spAtk,
        public readonly int $spDef,
        public readonly int $speed,
        /** @var TipoPokemon[] */
        public readonly array $tipos,
        public readonly Posicion $posicion,
        public readonly array $moves,
        public readonly bool $shiny = false,
        public readonly string $iconName = '',
        /** @var string[] */
        public readonly array $effectKeys = [],
        public readonly ?string $item = null,
        public readonly int $speciesId = 0,
        public readonly string $formSuffix = '',
        public readonly ?int $nivel = null,
        ?StatsValue $evs = null,
        ?StatsValue $ivs = null,
        public readonly PosturaCombate $postura = PosturaCombate::ATACAR,
        public readonly bool $esJefe = false,
        /** @var list<array<string, mixed>> */
        public readonly array $fasesJefe = [],
        /** @var list<array<string, mixed>> */
        public readonly array $puntosDebiles = [],
        /** Porcentaje de bonus inicial aplicado a todas las estadísticas (0 = sin bonus). */
        public readonly float $bonusInicialStats = 0.0,
        public readonly float $peso = 0.0,
        public readonly ?MapaSendaJugador $senda = null,
    ) {
        $this->evs = $evs ?? StatsValue::ceros();
        $this->ivs = $ivs ?? GeneradorIvs::aleatorios();

        foreach ($moves as $m) {
            if (! $m instanceof MovimientoBatalla) {
                throw new \InvalidArgumentException('Moves must be MovimientoBatalla instances');
            }
        }
        foreach ($tipos as $t) {
            if (! $t instanceof TipoPokemon) {
                throw new \InvalidArgumentException('Types must be TipoPokemon instances');
            }
        }
    }
}
