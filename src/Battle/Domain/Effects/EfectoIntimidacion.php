<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/**
 * Intimidación: al entrar en batalla reduce una etapa el Ataque de todos los
 * rivales.
 */
class EfectoIntimidacion implements InterfazEfecto
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    public function __construct(
        private readonly string $clave,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $rival = $this->equipoRivalDe($battle, $portador);
        if ($rival === null) {
            return;
        }

        $cambio = CambioStat::desdeEtapas(StatClave::ATAQUE, -1);
        $impulso = CambioStat::desdeEtapas(StatClave::ATAQUE, 1);
        foreach ($rival->combatientesCollection()->vivos() as $objetivo) {
            $usar = $objetivo->tieneEfecto('perro_guardian') ? $impulso : $cambio;
            $objetivo->setMultiplicadores($usar->aplicadoTemporalEn($objetivo->multiplicadores()));
        }

        $battle->agregarLog("¡{$portador->nombre()} intimida a los rivales!");
    }
}
