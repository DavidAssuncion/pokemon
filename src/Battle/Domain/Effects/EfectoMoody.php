<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Mal Humor: sube mucho una estadística y baja otra al final de la ronda. */
class EfectoMoody implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    private const STATS = [
        StatClave::ATAQUE,
        StatClave::DEFENSA,
        StatClave::ATAQUE_ESPECIAL,
        StatClave::DEFENSA_ESPECIAL,
        StatClave::VELOCIDAD,
    ];

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onRoundEnd(Combatiente $portador, AgregadoBatalla $battle): void
    {
        if (! $portador->estaVivo()) {
            return;
        }

        $sube = self::STATS[mt_rand(0, count(self::STATS) - 1)];
        $baja = self::STATS[mt_rand(0, count(self::STATS) - 1)];
        $portador->setMultiplicadores(CambioStat::desdeEtapas($sube, 2)->aplicadoTemporalEn($portador->multiplicadores()));
        if ($baja !== $sube) {
            $portador->setMultiplicadores(CambioStat::desdeEtapas($baja, -1)->aplicadoTemporalEn($portador->multiplicadores()));
        }
        $battle->agregarLog("{$portador->nombre()} cambia sus estadísticas por Mal Humor");
    }
}
