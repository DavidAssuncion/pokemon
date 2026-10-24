<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Habilidades de las Ruinas: debilitan una estadística de todos los rivales. */
class EfectoRuin implements InterfazEfecto
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    public function __construct(
        private readonly string $clave,
        private readonly StatClave $stat,
        private readonly int $etapas = -1,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->equipoRivalDe($battle, $portador)?->combatientesCollection()->vivos() ?? [] as $rival) {
            $rival->setMultiplicadores(CambioStat::desdeEtapas($this->stat, $this->etapas)->aplicadoTemporalEn($rival->multiplicadores()));
        }
        $battle->agregarLog("{$portador->nombre()} activa {$this->clave}");
    }
}
