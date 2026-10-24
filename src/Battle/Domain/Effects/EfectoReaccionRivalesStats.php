<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDanioRecibido;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/** Pelusa: al recibir daño reduce la Velocidad de todos los rivales. */
class EfectoReaccionRivalesStats implements InterfazEfecto, ReaccionaAlDanioRecibido
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    public function __construct(
        private readonly string $clave,
        private readonly StatClave $stat = StatClave::VELOCIDAD,
        private readonly int $etapas = -1,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function alRecibirDanio(Combatiente $portador, AccionBatalla $accion, ResultadoDanio $resultado, AgregadoBatalla $battle): void
    {
        if (! $resultado->huboDano()) {
            return;
        }

        foreach ($this->equipoRivalDe($battle, $portador)?->combatientesCollection()->vivos() ?? [] as $rival) {
            $rival->setMultiplicadores(CambioStat::desdeEtapas($this->stat, $this->etapas)->aplicadoTemporalEn($rival->multiplicadores()));
        }
    }
}
