<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDebilitamiento;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;

/**
 * Daño a los rivales cuando su portador cae debilitado (Detonación: 25 % de
 * los PS máximos del rival).
 */
class EfectoAlDebilitar implements InterfazEfecto, ReaccionaAlDebilitamiento
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    public function __construct(
        private readonly string $clave,
        private readonly float $fraccion,
        private readonly bool $vaDirectoAHp = true,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function alDebilitar(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $rival = $this->equipoRivalDe($battle, $portador);
        if ($rival === null) {
            return;
        }

        foreach ($rival->combatientesCollection()->vivos() as $objetivo) {
            $cantidad = $objetivo->pokemon()->battleStats()->hp * $this->fraccion;
            if ($cantidad <= 0.0) {
                continue;
            }

            $objetivo->recibirDaño($cantidad, false, $this->vaDirectoAHp ? 1.0 : 0.0);
            $battle->agregarLog("{$portador->nombre()} detona y causa ".round($cantidad)." de daño a {$objetivo->nombre()}");

            if (! $objetivo->estaVivo()) {
                $battle->agregarLog("¡{$objetivo->nombre()} se ha debilitado por la detonación!");
            }
        }
    }
}
