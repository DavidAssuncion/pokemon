<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Impulso: sube Velocidad al final de cada ronda. */
class EfectoImpulso implements InterfazEfecto
{
    use ComportamientosPorDefecto;

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

        $cambio = CambioStat::desdeEtapas(StatClave::VELOCIDAD, 1);
        $portador->setMultiplicadores($cambio->aplicadoTemporalEn($portador->multiplicadores()));
    }
}
