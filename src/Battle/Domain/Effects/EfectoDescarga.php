<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Descarga: sube Ataque o At. Especial según la defensa más débil del rival. */
class EfectoDescarga implements InterfazEfecto
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $rival = $this->equipoRivalDe($battle, $portador)?->combatientesCollection()->vivos()->first();
        if ($rival === null) {
            return;
        }

        $stat = $rival->pokemon()->battleStats()->defense <= $rival->pokemon()->battleStats()->spDef
            ? StatClave::ATAQUE
            : StatClave::ATAQUE_ESPECIAL;
        $portador->setMultiplicadores(CambioStat::desdeEtapas($stat, 1)->aplicadoTemporalEn($portador->multiplicadores()));
        $battle->agregarLog("{$portador->nombre()} analiza al rival y sube su {$stat->label()}");
    }
}
