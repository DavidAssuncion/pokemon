<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Chain\ManejadorDanioBase;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\PuedeContraatacar;
use Src\Battle\Domain\Enums\StatClave;

/**
 * Contraataque: responde a un ataque con potencia 30 física o 40 especial,
 * según el mayor stat ofensivo del portador. No exige parada perfecta.
 */
class EfectoContraataque implements InterfazEfecto, PuedeContraatacar
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly int $potenciaFisica = 30,
        private readonly int $potenciaEspecial = 40,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function contraatacar(
        Combatiente $portador,
        AccionBatalla $accion,
        float $danoRecibido,
        AgregadoBatalla $battle,
    ): void {
        if (! $portador->estaVivo() || $danoRecibido <= 0.0) {
            return;
        }

        $atk = $portador->obtenerStatEfectivo(StatClave::ATAQUE);
        $spAtk = $portador->obtenerStatEfectivo(StatClave::ATAQUE_ESPECIAL);
        $esEspecial = $spAtk > $atk;

        $potencia = $esEspecial ? $this->potenciaEspecial : $this->potenciaFisica;
        $ataque = $esEspecial ? $spAtk : $atk;
        $defensa = $accion->attacker->obtenerStatEfectivo(
            $esEspecial ? StatClave::DEFENSA_ESPECIAL : StatClave::DEFENSA,
        );

        $daño = max(1, ManejadorDanioBase::formulaBase(50, $potencia, $ataque, $defensa));
        $accion->attacker->recibirDaño($daño, $esEspecial, 0.0);

        $battle->agregarLog("{$portador->nombre()} contraataca a {$accion->attacker->nombre()} con ".round($daño).' de daño');
    }
}
