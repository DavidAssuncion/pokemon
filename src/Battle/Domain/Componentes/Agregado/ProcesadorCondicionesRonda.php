<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Agregado;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Ejecucion\CalculadoraCuracionAliada;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/**
 * Condiciones volátiles al final de la ronda: daño, curación, duración,
 * muerte y log. Sin estado.
 */
final class ProcesadorCondicionesRonda
{
    public function __construct(
        private readonly CalculadoraCuracionAliada $calculadora = new CalculadoraCuracionAliada(),
    ) {
    }

    public function procesar(AgregadoBatalla $batalla, Combatiente $portador): void
    {
        foreach ($portador->condiciones()->all() as $condicion) {
            $this->aplicar($batalla, $portador, $condicion);

            if ($portador->hpActual() <= 0.0) {
                $batalla->agregarLog("¡{$portador->nombre()} se ha debilitado por una condición!");
                $portador->notificarDebilitamiento($batalla);

                break;
            }
        }
    }

    private function aplicar(
        AgregadoBatalla $batalla,
        Combatiente $portador,
        CondicionVolatil $condicion,
    ): void {
        if ($this->esDeDano($condicion->clave)) {
            $this->aplicarDanioResidual($batalla, $portador, $condicion);
            $this->aplicarContadorMortal($batalla, $portador, $condicion);

            return;
        }

        $this->aplicarDuracionSimple($batalla, $portador, $condicion);
        $this->aplicarContadores($portador, $condicion);
        $this->aplicarDuracionEspecial($portador, $condicion);
    }

    private function esDeDano(ClaveCondicion $clave): bool
    {
        return in_array($clave, [
            ClaveCondicion::DRENADORAS,
            ClaveCondicion::MALDICION,
            ClaveCondicion::ATRAPADO,
            ClaveCondicion::CANTO_MORTAL,
            ClaveCondicion::MARIONETA_INERTE,
        ], true);
    }

    private function aplicarDanioResidual(
        AgregadoBatalla $batalla,
        Combatiente $portador,
        CondicionVolatil $condicion,
    ): void {
        switch ($condicion->clave) {
            case ClaveCondicion::DRENADORAS:
                $dano = $portador->pokemon()->battleStats()->hp
                    * ($condicion->fraccionDano > 0.0 ? $condicion->fraccionDano : 0.125);
                $resultado = $portador->recibirDaño($dano, true, 1.0);
                $origen = $this->buscar($batalla, $condicion->origenId);
                if ($origen !== null && $origen->estaVivo() && $resultado->danoHp > 0.0) {
                    $origen->curarHpCantidad($resultado->danoHp);
                }
                $batalla->agregarLog("{$portador->nombre()} pierde PS por Drenadoras");

                break;

            case ClaveCondicion::MALDICION:
                $dano = $portador->pokemon()->battleStats()->hp
                    * ($condicion->fraccionDano > 0.0 ? $condicion->fraccionDano : 0.0625);
                $portador->recibirDaño($dano, true, 1.0);
                $batalla->agregarLog("{$portador->nombre()} sufre la maldición");

                break;

            case ClaveCondicion::ATRAPADO:
                $dano = $portador->pokemon()->battleStats()->hp
                    * ($condicion->fraccionDano > 0.0 ? $condicion->fraccionDano : 0.125);
                $portador->recibirDaño($dano, ! $condicion->esFisico, 0.0);
                $condicion->turnos--;
                if ($condicion->turnos <= 0) {
                    $portador->quitarCondicion(ClaveCondicion::ATRAPADO);
                }
                $batalla->agregarLog("{$portador->nombre()} sufre daño por estar atrapado");

                break;
        }
    }

    private function aplicarContadorMortal(
        AgregadoBatalla $batalla,
        Combatiente $portador,
        CondicionVolatil $condicion,
    ): void {
        switch ($condicion->clave) {
            case ClaveCondicion::CANTO_MORTAL:
                $condicion->turnos--;
                if ($condicion->turnos <= 0) {
                    $portador->setHpActual(0);
                    $batalla->agregarLog("¡{$portador->nombre()} cae por Canto Mortal!");
                }

                break;

            case ClaveCondicion::MARIONETA_INERTE:
                $condicion->turnos--;
                if ($condicion->turnos <= 0) {
                    $portador->setHpActual(0.0);
                    $batalla->agregarLog("¡El control de {$portador->nombre()} termina!");
                }

                break;
        }
    }

    private function aplicarDuracionSimple(
        AgregadoBatalla $batalla,
        Combatiente $portador,
        CondicionVolatil $condicion,
    ): void {
        switch ($condicion->clave) {
            case ClaveCondicion::PROTEGIDO:
            case ClaveCondicion::AGUANTE:
                $portador->quitarCondicion($condicion->clave);

                break;

            case ClaveCondicion::AQUA_RING:
                if ($condicion->curacionFija > 0.0) {
                    $curacion = $this->calculadora->curacionConAporteFijo(
                        $portador->pokemon()->battleStats()->hp,
                        $condicion->curacionFija,
                    );
                    $recuperado = (int) round($portador->curarHpCantidad($curacion));
                    $batalla->agregarLog("{$portador->nombre()} recupera {$recuperado} PS por Acua Aro");

                    break;
                }

                $portador->curarConExcedente($portador->pokemon()->battleStats()->hp * 0.0625);
                $batalla->agregarLog("{$portador->nombre()} recupera PS por Acua Aro");

                break;

            case ClaveCondicion::BUCLE_ARENA:
            case ClaveCondicion::GIRO_FUEGO:
                $this->regenerarEscudo($batalla, $portador, $condicion);

                break;

            case ClaveCondicion::INTERRUPCION:
            case ClaveCondicion::ATURDIDO:
            case ClaveCondicion::RECARGA:
            case ClaveCondicion::FLINCH:
            case ClaveCondicion::CONFUSION:
                break;
        }
    }

    private function aplicarContadores(Combatiente $portador, CondicionVolatil $condicion): void
    {
        switch ($condicion->clave) {
            case ClaveCondicion::LEVITACION:
            case ClaveCondicion::DESAFIO:
            case ClaveCondicion::SILENCIO:
            case ClaveCondicion::MARCADO:
            case ClaveCondicion::VINCULO_DANO:
            case ClaveCondicion::EMBARGO:
            case ClaveCondicion::IGNORA_HABILIDAD:
            case ClaveCondicion::VULNERABLE:
            case ClaveCondicion::MOFA:
            case ClaveCondicion::CRITICO_MEJORADO:
                $this->reducirYQuitar($portador, $condicion);

                break;

            case ClaveCondicion::AQUA_RING:
            case ClaveCondicion::BUCLE_ARENA:
            case ClaveCondicion::GIRO_FUEGO:
                if ($condicion->turnos > 0) {
                    $this->reducirYQuitar($portador, $condicion);
                }

                break;
        }
    }

    private function aplicarDuracionEspecial(Combatiente $portador, CondicionVolatil $condicion): void
    {
        switch ($condicion->clave) {
            case ClaveCondicion::TIPO_ALTERADO:
                if ($condicion->turnos > 0) {
                    $this->reducirYQuitar($portador, $condicion);
                }

                break;

            case ClaveCondicion::HABILIDAD_ANULADA:
                $condicion->turnos--;
                if ($condicion->turnos <= 0) {
                    $portador->restaurarHabilidad();
                }

                break;

            case ClaveCondicion::SUSTITUTO:
                if ($condicion->turnos > 0) {
                    $this->reducirYQuitar($portador, $condicion);
                }

                break;
        }
    }

    private function regenerarEscudo(
        AgregadoBatalla $batalla,
        Combatiente $portador,
        CondicionVolatil $condicion,
    ): void {
        if ($condicion->turnos <= 0
            || ! is_finite($condicion->curacionFija)
            || $condicion->curacionFija <= 0.0) {
            return;
        }

        $esFisico = $condicion->clave === ClaveCondicion::BUCLE_ARENA;

        $maxEscudo = $esFisico
            ? $portador->pokemon()->battleStats()->defenseHp
            : $portador->pokemon()->battleStats()->spDefenseHp;

        $actual = $esFisico
            ? $portador->defensaHpActual()
            : $portador->defensaEspHpActual();

        $nuevo = max($actual, min($maxEscudo, $actual + $condicion->curacionFija));
        $recuperado = (int) round($nuevo - $actual);

        if ($esFisico) {
            $portador->setDefensaHpActual($nuevo);
        } else {
            $portador->setDefensaEspHpActual($nuevo);
        }

        $etiqueta = $esFisico ? 'DEF' : 'DEF.ESP';
        $nombre = $esFisico ? 'Bucle Arena' : 'Giro Fuego';

        $batalla->agregarLog($recuperado > 0
            ? "{$portador->nombre()} recupera {$recuperado} de {$etiqueta} por {$nombre}"
            : "{$portador->nombre()} ya tiene la {$etiqueta} al máximo ({$nombre})");
    }

    private function reducirYQuitar(Combatiente $portador, CondicionVolatil $condicion): void
    {
        $condicion->turnos--;
        if ($condicion->turnos <= 0) {
            $portador->quitarCondicion($condicion->clave);
        }
    }

    private function buscar(AgregadoBatalla $batalla, string $id): ?Combatiente
    {
        foreach ($batalla->turnManager()->allCombatants() as $combatiente) {
            if ($combatiente->id() === $id) {
                return $combatiente;
            }
        }

        return null;
    }
}
