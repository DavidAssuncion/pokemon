<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Ejecucion;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Danio\GestorEfectosMovimiento;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\Enums\TipoEfectoAliado;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Battle\Domain\ValueObjects\EfectoAliado;

/**
 * Aplica un efecto de apoyo sobre un aliado vivo sin pasar por daño, precisión,
 * crítico, inmunidades ni reacciones. Devuelve el texto para el evento.
 */
final class EjecutorEfectoAliado
{
    private const FRACCION_APORTE_AQUA_RING = 0.05;

    private const FRACCION_RECUPERACION = 0.5;

    public function __construct(
        private readonly GestorEfectosMovimiento $gestorEfectos = new GestorEfectosMovimiento(),
        private readonly CalculadoraCuracionAliada $calculadora = new CalculadoraCuracionAliada(),
    ) {
    }

    public function ejecutar(AccionBatalla $accion, EfectoAliado $efecto, AgregadoBatalla $battle): string
    {
        if (! $accion->defender->estaVivo()) {
            $texto = "{$accion->defender->nombre()} ya no puede recibir el efecto";
            $battle->agregarLog($texto);

            return $texto;
        }

        return match ($efecto->tipo) {
            TipoEfectoAliado::ESCUDO_FISICO,
            TipoEfectoAliado::ESCUDO_ESPECIAL => $this->aplicarEscudo($accion, $efecto, $battle),
            TipoEfectoAliado::AQUA_RING => $this->aplicarAquaRing($accion, $efecto, $battle),
            TipoEfectoAliado::CURAR_PS => $this->aplicarCuracionPs($accion, $battle),
            TipoEfectoAliado::BUFF_STATS => $this->aplicarBuffs($accion, $battle),
            TipoEfectoAliado::CURAR_ESTADO => $this->aplicarCuracionEstado($accion, $battle),
            TipoEfectoAliado::CRITICO_MEJORADO => $this->aplicarCriticoMejorado($accion, $efecto, $battle),
        };
    }

    private function aplicarEscudo(
        AccionBatalla $accion,
        EfectoAliado $efecto,
        AgregadoBatalla $battle,
    ): string {
        $aliado = $accion->defender;
        $lanzador = $accion->attacker;

        $especial = $efecto->tipo === TipoEfectoAliado::ESCUDO_ESPECIAL;

        $maxEscudo = $especial
            ? $aliado->pokemon()->battleStats()->spDefenseHp
            : $aliado->pokemon()->battleStats()->defenseHp;
        $statLanzador = $especial ? StatClave::DEFENSA_ESPECIAL : StatClave::DEFENSA;

        $incremento = $this->calculadora->incrementoEscudo(
            $maxEscudo,
            $lanzador->obtenerStatEfectivo($statLanzador),
        );

        $actual = $especial ? $aliado->defensaEspHpActual() : $aliado->defensaHpActual();
        $nuevo = min($maxEscudo, $actual + $incremento);
        $recuperado = (int) round($nuevo - $actual);

        if ($especial) {
            $aliado->setDefensaEspHpActual($nuevo);
        } else {
            $aliado->setDefensaHpActual($nuevo);
        }

        if ($efecto->turnos > 0) {
            $aliado->reemplazarCondicion(new CondicionVolatil(
                clave: $especial ? ClaveCondicion::GIRO_FUEGO : ClaveCondicion::BUCLE_ARENA,
                turnos: $efecto->turnos,
                origenId: $lanzador->id(),
                curacionFija: $incremento,
            ));
        }

        $etiqueta = $especial ? 'DEF.ESP' : 'DEF';

        if ($efecto->turnos > 0) {
            $texto = $recuperado > 0
                ? "{$aliado->nombre()} recupera {$recuperado} de {$etiqueta} y recuperará {$incremento} de {$etiqueta} al final de cada ronda durante {$efecto->turnos} rondas"
                : "{$aliado->nombre()} ya tiene la {$etiqueta} al máximo, pero recuperará {$incremento} de {$etiqueta} al final de cada ronda durante {$efecto->turnos} rondas";
        } else {
            $texto = $recuperado > 0
                ? "{$aliado->nombre()} recupera {$recuperado} de {$etiqueta}"
                : "{$aliado->nombre()} ya tiene la {$etiqueta} al máximo";
        }

        $battle->agregarLog($texto);

        return $texto;
    }

    private function aplicarCuracionPs(AccionBatalla $accion, AgregadoBatalla $battle): string
    {
        $aliado = $accion->defender;

        $fraccion = $accion->move->perfil->curacionFraccion;
        if ($fraccion <= 0.0) {
            $fraccion = self::FRACCION_RECUPERACION;
        }

        $curacion = $aliado->pokemon()->battleStats()->hp * $fraccion;
        $recuperado = (int) round($aliado->curarHpCantidad($curacion));

        $texto = $recuperado > 0
            ? "{$aliado->nombre()} recupera {$recuperado} PS"
            : "{$aliado->nombre()} ya tiene los PS al máximo";

        $battle->agregarLog($texto);

        return $texto;
    }

    private function aplicarAquaRing(
        AccionBatalla $accion,
        EfectoAliado $efecto,
        AgregadoBatalla $battle,
    ): string {
        $aliado = $accion->defender;
        $lanzador = $accion->attacker;

        $aporte = $lanzador->pokemon()->battleStats()->hp * self::FRACCION_APORTE_AQUA_RING;

        $aliado->reemplazarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::AQUA_RING,
            turnos: $efecto->turnos,
            origenId: $lanzador->id(),
            curacionFija: $aporte,
        ));

        $curacion = $this->calculadora->curacionConAporteFijo(
            $aliado->pokemon()->battleStats()->hp,
            $aporte,
        );
        $texto = "{$aliado->nombre()} queda rodeado por Acua Aro "
            ."({$curacion} PS por ronda durante {$efecto->turnos} rondas)";

        $battle->agregarLog($texto);

        return $texto;
    }

    private function aplicarBuffs(AccionBatalla $accion, AgregadoBatalla $battle): string
    {
        $aliado = $accion->defender;
        $movimiento = $accion->move;

        $this->gestorEfectos->aplicarStatChangesAMuchos($aliado, [], $movimiento);

        $lineas = [];
        foreach ($movimiento->selfStatChanges as $cambio) {
            $factor = $cambio->factorTemporal();
            $verbo = $factor >= 1.0 ? 'subió' : 'bajó';
            $porcentaje = (int) round(abs(($factor - 1.0) * 100));
            $linea = "{$aliado->nombre()} {$verbo} {$cambio->stat->label()} en {$porcentaje}%!";

            $battle->agregarLog($linea);
            $lineas[] = $linea;
        }

        if ($lineas === []) {
            $texto = "{$aliado->nombre()} no ve cambios en sus estadísticas";

            $battle->agregarLog($texto);

            return $texto;
        }

        return implode(' ', $lineas);
    }

    private function aplicarCuracionEstado(AccionBatalla $accion, AgregadoBatalla $battle): string
    {
        $aliado = $accion->defender;
        $teniaEstado = $aliado->estadoMayor() !== EstadoPokemon::NONE;

        $aliado->curarEstadoMayor();

        $texto = $teniaEstado
            ? "{$aliado->nombre()} se recupera de su estado alterado"
            : "{$aliado->nombre()} no tiene ningún estado alterado";

        $battle->agregarLog($texto);

        return $texto;
    }

    private function aplicarCriticoMejorado(
        AccionBatalla $accion,
        EfectoAliado $efecto,
        AgregadoBatalla $battle,
    ): string {
        $aliado = $accion->defender;
        $turnos = max(1, $efecto->turnos);

        $aliado->reemplazarCondicion(new CondicionVolatil(
            clave: ClaveCondicion::CRITICO_MEJORADO,
            turnos: $turnos,
            origenId: $accion->attacker->id(),
        ));

        $texto = "{$aliado->nombre()} concentra su energía "
            ."(crítico mejorado durante {$turnos} rondas)";

        $battle->agregarLog($texto);

        return $texto;
    }
}
