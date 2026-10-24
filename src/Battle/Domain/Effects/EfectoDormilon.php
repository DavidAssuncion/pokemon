<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Chain\ManejadorDanioBase;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Danio\AplicadorDanioObjetivo;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDanioRecibido;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Dormilón: al bajar del 33 % de HP por primera vez se duerme un turno y
 * recupera el 50 % de su HP. Mientras duerme, cada golpe recibido emite un
 * ronquido de 30 de potencia contra todos los rivales vivos.
 */
class EfectoDormilon implements InterfazEfecto, ReaccionaAlDanioRecibido
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    private bool $usado = false;

    private bool $dormido = false;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function alRecibirDanio(
        Combatiente $portador,
        AccionBatalla $accion,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        if (! $portador->estaVivo() || ! $resultado->huboDano()) {
            return;
        }

        $maxHp = $portador->pokemon()->battleStats()->hp;
        if (! $this->usado && $maxHp > 0.0 && $portador->hpActual() <= $maxHp * 0.33) {
            $this->usado = true;
            $this->dormido = true;
            $portador->setEstado(EstadoPokemon::SLEEP);
            $portador->setTurnosEstado(1);
            $portador->curarConExcedente($maxHp * 0.5);
            $battle->agregarLog("¡{$portador->nombre()} se duerme por Dormilón y recupera vida!");

            return;
        }

        if (! $this->dormido || $portador->estado() !== EstadoPokemon::SLEEP) {
            return;
        }

        $rival = $this->equipoRivalDe($battle, $portador);
        if ($rival === null) {
            return;
        }

        $movimiento = new MovimientoBatalla(
            nombre: 'Ronquido',
            potencia: 30,
            tipo: TipoPokemon::NORMAL,
            categoria: CategoriaMovimiento::ESPECIAL,
        );
        $aplicador = new AplicadorDanioObjetivo();

        foreach ($rival->combatientesCollection()->vivos() as $objetivo) {
            $dano = max(1, (int) floor(ManejadorDanioBase::formulaBase(
                50,
                30,
                $portador->obtenerStatEfectivo(StatClave::ATAQUE_ESPECIAL),
                $objetivo->obtenerStatEfectivo(StatClave::DEFENSA_ESPECIAL),
            )));
            $equipoObjetivo = $battle->equipoDe($objetivo);
            $accionGolpe = new AccionBatalla(
                attacker: $portador,
                defender: $objetivo,
                move: $movimiento,
                defenderTeamHasVanguard: $equipoObjetivo?->tieneVanguardiaViva() ?? false,
                weather: $battle->weather(),
                equipoDefensor: $equipoObjetivo,
                terreno: $battle->terreno(),
            );

            $resultadoGolpe = $aplicador->aplicar(
                $objetivo,
                $movimiento,
                $dano,
                0.0,
                $movimiento->perfil,
                $battle,
                $accionGolpe,
            );

            if ($resultadoGolpe->huboDano()) {
                $dañoAplicado = $resultadoGolpe->danoAplicado();
                $battle->subject()->notifyDamaged($objetivo, $dañoAplicado);
                $objetivo->dispararDanioRecibido($dañoAplicado, $battle);
                $objetivo->dispararDanioRecibidoCompleto($accionGolpe, $resultadoGolpe, $battle);

                if ($resultadoGolpe->rompioEscudo) {
                    $objetivo->dispararEscudoRoto($portador, $resultadoGolpe, $battle);
                    $portador->dispararEscudoRoto($objetivo, $resultadoGolpe, $battle);
                }

                $battle->agregarLog(
                    "{$portador->nombre()} ronca y causa ".round($resultadoGolpe->danoAplicado())
                    ." de daño a {$objetivo->nombre()}",
                );
            }

            if (! $objetivo->estaVivo()) {
                $objetivo->notificarDebilitamiento($battle);
            }
        }
    }
}
