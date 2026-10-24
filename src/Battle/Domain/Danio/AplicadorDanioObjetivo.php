<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Danio;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * Aplica daño a un objetivo concreto: Guardia/parada, vínculo, sustituto,
 * barreras/HP y activación de fases del jefe.
 */
final class AplicadorDanioObjetivo
{
    public function __construct(
        private readonly RepartidorVinculoDanio $repartidorVinculo = new RepartidorVinculoDanio(),
    ) {
    }

    public function aplicar(
        Combatiente $objetivo,
        MovimientoBatalla $movimiento,
        float $daño,
        float $directPct,
        PerfilMovimiento $perfil,
        ?AgregadoBatalla $battle,
        AccionBatalla $accion,
    ): ResultadoDanio {
        if ($daño <= 0.0) {
            return ResultadoDanio::sinDano();
        }

        if ($objetivo->postura() === PosturaCombate::GUARDIA) {
            $chance = $this->probabilidadParadaPerfecta($objetivo, $movimiento);
            if (mt_rand() / mt_getrandmax() < $chance) {
                $battle?->agregarLog("{$objetivo->nombre()} hace una parada perfecta");
                $daño = 0.0;
            } else {
                $daño *= 0.5;
                $battle?->agregarLog("{$objetivo->nombre()} reduce el daño con Guardia");
            }
        }

        if ($daño <= 0.0) {
            return ResultadoDanio::sinDano();
        }

        if ($battle !== null) {
            $daño = $this->repartidorVinculo->repartir(
                $objetivo,
                $movimiento,
                $daño,
                $directPct,
                $perfil,
                $battle,
                $accion,
            );
        }

        if ($daño <= 0.0) {
            return ResultadoDanio::sinDano();
        }

        if ($battle !== null && ! $accion->attacker->tieneEfecto('allanamiento')) {
            $sustituto = $objetivo->absorberConSustituto($daño);
            if ($sustituto['absorbido'] > 0.0) {
                $battle->agregarLog(
                    "El sustituto de {$objetivo->nombre()} absorbe ".round($sustituto['absorbido']).' de daño',
                );
                if ($sustituto['roto']) {
                    $battle->agregarLog("¡El sustituto de {$objetivo->nombre()} se rompe!");
                }
            }

            $daño = $sustituto['restante'];
        }

        if ($daño <= 0.0) {
            return ResultadoDanio::sinDano();
        }

        $resultado = $objetivo->recibirDaño(
            $daño,
            $movimiento->esEspecial(),
            $directPct,
            $perfil->multiplicadorDanoEscudo * $accion->attacker->multiplicadorDanioEscudo($accion),
            $perfil->multiplicadorDanoSalud,
        );

        $objetivo->registrarDanoRecibido($resultado->danoAplicado(), $accion->attacker->id());
        $battle?->equipoDe($objetivo)?->consumirPantallas($resultado->danoAplicado(), $movimiento);

        if ($battle !== null && $resultado->danoAplicado() > 0.0) {
            $objetivo->procesarDanoJefe($resultado->danoAplicado(), $battle);
        }

        return $resultado;
    }

    private function probabilidadParadaPerfecta(Combatiente $objetivo, MovimientoBatalla $movimiento): float
    {
        $defensa = $objetivo->obtenerStatEfectivo(
            $movimiento->esEspecial() ? StatClave::DEFENSA_ESPECIAL : StatClave::DEFENSA,
        );
        $ataque = $objetivo->obtenerStatEfectivo(
            $movimiento->esEspecial() ? StatClave::ATAQUE_ESPECIAL : StatClave::ATAQUE,
        );

        if ($defensa + $ataque <= 0.0) {
            return 0.0;
        }

        return min(0.25, 0.05 + 0.20 * ($defensa / ($defensa + $ataque)));
    }
}
