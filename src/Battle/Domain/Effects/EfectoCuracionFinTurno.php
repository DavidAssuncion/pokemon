<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;
use Src\Battle\Domain\Enums\TipoClima;

/**
 * Curación al final de la ronda con desbordamiento a escudo.
 * Cubre Regeneración/Síntesis/Bendición (1/8), Protector/Campo Magnético
 * (1/16 al equipo) y Fotosíntesis/Manto Helado (condicionadas por clima).
 */
class EfectoCuracionFinTurno implements InterfazEfecto
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    public function __construct(
        private readonly string $clave,
        private readonly float $fraccion,
        private readonly bool $aTodoElEquipo = false,
        private readonly ?TipoClima $clima = null,
        private readonly bool $excedenteAEscudoEspecial = true,
    ) {
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

        if ($this->clima !== null && $portador->clima() !== $this->clima) {
            return;
        }

        if ($this->aTodoElEquipo) {
            $equipo = $this->equipoDe($battle, $portador);
            if ($equipo === null) {
                return;
            }

            foreach ($equipo->combatientesCollection()->vivos() as $aliado) {
                $this->curar($aliado, $battle);
            }

            return;
        }

        $this->curar($portador, $battle);
    }

    private function curar(Combatiente $objetivo, AgregadoBatalla $battle): void
    {
        $cantidad = $objetivo->pokemon()->battleStats()->hp * $this->fraccion;
        if ($cantidad <= 0.0) {
            return;
        }

        $objetivo->curarConExcedente($cantidad, $this->excedenteAEscudoEspecial);
        $battle->agregarLog("{$objetivo->nombre()} recupera ".round($cantidad).' PS');
    }
}
