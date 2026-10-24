<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Danio;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoSafeguard;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoVeloDulce;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoVeloFlor;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoVeloPastel;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Battle\Domain\ValueObjects\EfectoSecundario;

/**
 * Aplica los efectos de un movimiento: estado, control, cambios de stats,
 * secundarios y autoefectos. No calcula daño ni decide objetivos.
 */
final class GestorEfectosMovimiento
{
    public function aplicarEstado(
        Combatiente $objetivo,
        MovimientoBatalla $movimiento,
        ?AgregadoBatalla $battle = null,
        ?Combatiente $atacante = null,
    ): void {
        if (! $movimiento->tieneStatus() || ! $objetivo->estaVivo()) {
            return;
        }

        if ($objetivo->tieneCondicion(\Src\Battle\Domain\Enums\ClaveCondicion::PROTEGIDO)) {
            return;
        }

        if ($movimiento->perfil->probabilidadEstado < 1.0
            && mt_rand() / mt_getrandmax() >= $movimiento->perfil->probabilidadEstado) {
            return;
        }

        if ($battle !== null) {
            foreach ($battle->equipoDe($objetivo)?->efectos()->all() ?? [] as $efectoEquipo) {
                if ($efectoEquipo instanceof EfectoEquipoVeloFlor
                    && $efectoEquipo->protegeA($objetivo, $movimiento->statusEffect)) {
                    return;
                }
                if ($efectoEquipo instanceof EfectoEquipoVeloDulce
                    && $efectoEquipo->bloqueaEstado($movimiento->statusEffect)) {
                    return;
                }
                if ($efectoEquipo instanceof EfectoEquipoVeloPastel
                    && $efectoEquipo->bloqueaEstado($movimiento->statusEffect)) {
                    return;
                }
                if ($efectoEquipo instanceof EfectoEquipoSafeguard && $efectoEquipo->estaActivo()) {
                    return;
                }
            }
        }

        if ($battle !== null && ! $objetivo->effects()->permiteEstado($objetivo, $movimiento->statusEffect, $battle)) {
            return;
        }

        $objetivo->setEstado($movimiento->statusEffect);
        $objetivo->setTurnosEstado(match ($movimiento->statusEffect) {
            EstadoPokemon::SLEEP => mt_rand(2, 4),
            EstadoPokemon::CONFUSION => mt_rand(2, 4),
            default => 0,
        });

        if ($objetivo->tieneEfecto('madrugar') && $movimiento->statusEffect === EstadoPokemon::SLEEP) {
            $objetivo->setTurnosEstado(max(1, intdiv($objetivo->turnosEstado(), 2)));
        }

        if ($objetivo->tieneEfecto('sincronia') && $atacante !== null && $atacante->estado() === EstadoPokemon::NONE
            && in_array($movimiento->statusEffect, [EstadoPokemon::BURN, EstadoPokemon::POISON, EstadoPokemon::BAD_POISON, EstadoPokemon::PARALYSIS], true)) {
            $atacante->setEstado($movimiento->statusEffect);
        }

        if ($movimiento->statusEffect === EstadoPokemon::POISON
            || $movimiento->statusEffect === EstadoPokemon::BAD_POISON) {
            $objetivo->aplicarCargaVeneno($movimiento->perfil->cargasVeneno);
        }
    }

    public function aplicarControl(
        AccionBatalla $accion,
        MovimientoBatalla $movimiento,
        AgregadoBatalla $battle,
    ): void {
        $objetivo = $accion->defender;
        if (! $objetivo->estaVivo()) {
            return;
        }

        $perfil = $movimiento->perfil;

        if ($perfil->rompeGuardia && $objetivo->postura() === PosturaCombate::GUARDIA) {
            $objetivo->aturdir();
            $battle->agregarLog("¡{$movimiento->nombre} rompe la guardia de {$objetivo->nombre()}!");
        }

        if ($perfil->silencioTurnos > 0) {
            $objetivo->aplicarSilencio($perfil->silencioTurnos);
            $battle->agregarLog("¡{$objetivo->nombre()} quedó en silencio!");
        }

        if ($perfil->interrumpe) {
            $objetivo->aplicarInterrupcion();
            $battle->agregarLog("¡La acción de {$objetivo->nombre()} fue interrumpida!");
        }

        if ($perfil->aturde) {
            $objetivo->aturdir();
            $battle->agregarLog("¡{$objetivo->nombre()} quedó aturdido!");
        }
    }

    public function aplicarStatChanges(Combatiente $actor, Combatiente $objetivo, MovimientoBatalla $movimiento): void
    {
        $this->aplicarStatChangesAMuchos($actor, [$objetivo], $movimiento);
    }

    /**
     * Los cambios propios se aplican una sola vez; los del objetivo se aplican
     * a cada objetivo alcanzado por el movimiento.
     *
     * @param  list<Combatiente>  $objetivos
     */
    public function aplicarStatChangesAMuchos(Combatiente $actor, array $objetivos, MovimientoBatalla $movimiento): void
    {
        $veces = $movimiento->esEstado() ? (int) $actor->multiplicadorEfectoEstado() : 1;

        foreach ($movimiento->selfStatChanges as $cambio) {
            for ($i = 0; $i < $veces; $i++) {
                $this->aplicarCambioStat($actor, $cambio);
            }
        }

        foreach ($objetivos as $objetivo) {
            if ($objetivo->tieneCondicion(\Src\Battle\Domain\Enums\ClaveCondicion::PROTEGIDO)) {
                continue;
            }

            foreach ($movimiento->targetStatChanges as $cambio) {
                if ($cambio->factor < 1.0 && $objetivo->tieneEfecto('coraza_reflejo') && $actor !== $objetivo) {
                    $actor->setMultiplicadores(\Src\Battle\Domain\ValueObjects\CambioStat::desdeEtapas($cambio->stat, -$cambio->etapas)->aplicadoTemporalEn($actor->multiplicadores()));
                    continue;
                }

                if ($cambio->factor < 1.0 && $this->bloqueaBajada($objetivo, $cambio->stat)) {
                    continue;
                }

                $aplicado = $cambio;
                for ($i = 0; $i < $veces; $i++) {
                    $aplicado = $this->aplicarCambioStat($objetivo, $cambio);
                }

                if ($aplicado->factor < 1.0) {
                    if ($objetivo->tieneEfecto('tenacidad')) {
                        $objetivo->setMultiplicadores(\Src\Battle\Domain\ValueObjects\CambioStat::desdeEtapas(\Src\Battle\Domain\Enums\StatClave::ATAQUE_ESPECIAL, 2)->aplicadoTemporalEn($objetivo->multiplicadores()));
                    }
                    if ($objetivo->tieneEfecto('competitivo')) {
                        $objetivo->setMultiplicadores(\Src\Battle\Domain\ValueObjects\CambioStat::desdeEtapas(\Src\Battle\Domain\Enums\StatClave::ATAQUE, 2)->aplicadoTemporalEn($objetivo->multiplicadores()));
                    }
                }
            }
        }
    }

    public function aplicarSecundarios(
        AccionBatalla $accion,
        MovimientoBatalla $movimiento,
        AgregadoBatalla $battle,
    ): void {
        foreach ($movimiento->secundarios as $efecto) {
            $this->aplicarEfectoSecundario($accion, $efecto, false, $battle);
        }

        foreach ($movimiento->autoEfectos as $efecto) {
            $this->aplicarEfectoSecundario($accion, $efecto, true, $battle);
        }
    }

    private function aplicarEfectoSecundario(
        AccionBatalla $accion,
        EfectoSecundario $efecto,
        bool $auto,
        AgregadoBatalla $battle,
    ): void {
        $probabilidad = $efecto->probabilidad;
        if (! $auto && $accion->attacker->tieneEfecto('dicha')) {
            $probabilidad = min(100, $probabilidad * 2);
        }
        if ($probabilidad < 100 && mt_rand(1, 100) > $probabilidad) {
            return;
        }

        if (! $auto && $accion->defender->tieneEfecto('polvo_escudo')) {
            return;
        }

        $soloStatsAlCien = $efecto->probabilidad >= 100
            && $efecto->estado === EstadoPokemon::NONE
            && $efecto->condicionVolatil === null
            && ! $efecto->cambios->isEmpty();

        if ($soloStatsAlCien) {
            return;
        }

        $objetivo = $auto ? $accion->attacker : $accion->defender;
        if (! $objetivo->estaVivo()) {
            return;
        }

        if ($efecto->estado !== EstadoPokemon::NONE) {
            $bloqueadoPorEquipo = false;
            foreach ($battle->equipoDe($objetivo)?->efectos()->all() ?? [] as $efectoEquipo) {
                if ($efectoEquipo instanceof EfectoEquipoVeloFlor && $efectoEquipo->protegeA($objetivo, $efecto->estado)) {
                    $bloqueadoPorEquipo = true;
                }
                if ($efectoEquipo instanceof EfectoEquipoVeloDulce && $efectoEquipo->bloqueaEstado($efecto->estado)) {
                    $bloqueadoPorEquipo = true;
                }
                if ($efectoEquipo instanceof EfectoEquipoSafeguard && $efectoEquipo->estaActivo()) {
                    $bloqueadoPorEquipo = true;
                }
            }

            if (! $bloqueadoPorEquipo && $objetivo->effects()->permiteEstado($objetivo, $efecto->estado, $battle)) {
                $objetivo->setEstado($efecto->estado);
                $objetivo->setTurnosEstado(match ($efecto->estado) {
                    EstadoPokemon::SLEEP, EstadoPokemon::CONFUSION => mt_rand(2, 4),
                    default => 0,
                });

                if ($efecto->estado === EstadoPokemon::POISON
                    || $efecto->estado === EstadoPokemon::BAD_POISON) {
                    $objetivo->aplicarCargaVeneno(1);
                }

                if ($accion->attacker->tieneEfecto('cadena_toxica') && $objetivo->estado() === EstadoPokemon::NONE) {
                    $objetivo->setEstado(EstadoPokemon::POISON);
                    $objetivo->aplicarCargaVeneno(1);
                }

                if ($accion->attacker->tieneEfecto('titere_toxico') && $objetivo->estado() === EstadoPokemon::POISON) {
                    $objetivo->agregarCondicion(new CondicionVolatil(ClaveCondicion::CONFUSION, 2));
                }

                if ($objetivo->tieneEfecto('sincronia') && $accion->attacker->estado() === EstadoPokemon::NONE
                    && in_array($efecto->estado, [EstadoPokemon::BURN, EstadoPokemon::POISON, EstadoPokemon::BAD_POISON, EstadoPokemon::PARALYSIS], true)) {
                    $accion->attacker->setEstado($efecto->estado);
                    $accion->attacker->setTurnosEstado(0);
                }

                if ($objetivo->tieneEfecto('madrugar') && $efecto->estado === EstadoPokemon::SLEEP) {
                    $objetivo->setTurnosEstado(max(1, intdiv($objetivo->turnosEstado(), 2)));
                }
            }
        }

        foreach ($efecto->cambios as $cambio) {
            $objetivo->setMultiplicadores($cambio->aplicadoTemporalEn($objetivo->multiplicadores()));
        }

        if ($efecto->condicionVolatil !== null) {
            $clave = ClaveCondicion::desdePs($efecto->condicionVolatil);
            if ($clave !== null) {
                if ($clave === ClaveCondicion::FLINCH && $objetivo->tieneEfecto('intrepido')) {
                    return;
                }

                $turnos = match ($clave) {
                    ClaveCondicion::FLINCH => 1,
                    ClaveCondicion::CANTO_MORTAL, ClaveCondicion::DESAFIO => 3,
                    ClaveCondicion::ATRAPADO => mt_rand(2, 5),
                    default => 0,
                };

                if ($clave === ClaveCondicion::FLINCH && $objetivo->tieneEfecto('impasible')) {
                    $objetivo->setMultiplicadores(\Src\Battle\Domain\ValueObjects\CambioStat::desdeEtapas(\Src\Battle\Domain\Enums\StatClave::VELOCIDAD, 1)->aplicadoTemporalEn($objetivo->multiplicadores()));
                }

                $objetivo->agregarCondicion(new CondicionVolatil(
                    clave: $clave,
                    turnos: $turnos,
                    origenId: $accion->attacker->id(),
                    fraccionDano: match ($clave) {
                        ClaveCondicion::DRENADORAS => 0.125,
                        ClaveCondicion::MALDICION => 0.0625,
                        ClaveCondicion::ATRAPADO => 0.125,
                        default => 0.0,
                    },
                    vaDirectoAHp: in_array($clave, [ClaveCondicion::DRENADORAS, ClaveCondicion::MALDICION], true),
                    esFisico: $clave === ClaveCondicion::ATRAPADO,
                ));
            }
        }
    }

    private function aplicarCambioStat(Combatiente $portador, \Src\Battle\Domain\ValueObjects\CambioStat $cambio): \Src\Battle\Domain\ValueObjects\CambioStat
    {
        if ($portador->tieneEfecto('guardia_metalica') && $cambio->factor < 1.0) {
            return $cambio;
        }

        $aplicado = $cambio;
        if ($portador->tieneEfecto('inversion') && $cambio->etapas !== 0) {
            $aplicado = \Src\Battle\Domain\ValueObjects\CambioStat::desdeEtapas($cambio->stat, -$cambio->etapas);
        }

        $veces = $portador->tieneEfecto('simple') ? 2 : 1;
        for ($i = 0; $i < $veces; $i++) {
            $portador->setMultiplicadores($aplicado->aplicadoTemporalEn($portador->multiplicadores()));
        }

        return $aplicado;
    }

    private function bloqueaBajada(Combatiente $objetivo, \Src\Battle\Domain\Enums\StatClave $stat): bool
    {
        if ($objetivo->tieneEfecto('cuerpo_puro') || $objetivo->tieneEfecto('humo_blanco')) {
            return true;
        }

        return match ($stat) {
            \Src\Battle\Domain\Enums\StatClave::ATAQUE => $objetivo->tieneEfecto('corte_fuerte'),
            \Src\Battle\Domain\Enums\StatClave::DEFENSA => $objetivo->tieneEfecto('sacapecho'),
            \Src\Battle\Domain\Enums\StatClave::PRECISION => $objetivo->tieneEfecto('vista_lince') || $objetivo->tieneEfecto('ojo_mental'),
            default => false,
        };
    }
}
