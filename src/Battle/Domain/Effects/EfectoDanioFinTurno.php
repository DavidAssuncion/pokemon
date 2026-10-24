<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\ObjetivoEfecto;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * Daño o destrucción de escudos al final de la ronda.
 */
class EfectoDanioFinTurno implements InterfazEfecto
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    public function __construct(
        private readonly string $clave,
        private readonly float $fraccion,
        private readonly ObjetivoEfecto $objetivo = ObjetivoEfecto::PROPIO,
        private readonly bool $especial = true,
        private readonly bool $vaDirectoAHp = false,
        private readonly bool $afectaEscudos = false,
        private readonly ?TipoClima $clima = null,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onRoundEnd(Combatiente $portador, AgregadoBatalla $battle): void
    {
        if ($this->clima !== null && $portador->clima() !== $this->clima) {
            return;
        }

        foreach ($this->objetivos($portador, $battle) as $objetivo) {
            if (! $objetivo->estaVivo()) {
                continue;
            }

            if ($this->equipoProtegido($objetivo, $battle)) {
                continue;
            }

            if ($this->afectaEscudos) {
                $antesEsp = $objetivo->defensaEspHpActual();
                $antesFis = $objetivo->defensaHpActual();
                $destruidoEsp = $objetivo->daniarBarreras(
                    $objetivo->pokemon()->battleStats()->spDefenseHp * $this->fraccion,
                    true,
                );
                $destruidoFis = $objetivo->daniarBarreras(
                    $objetivo->pokemon()->battleStats()->defenseHp * $this->fraccion,
                    false,
                );
                $rompioEscudo = ($antesEsp > 0.0 && $objetivo->defensaEspHpActual() <= 0.0)
                    || ($antesFis > 0.0 && $objetivo->defensaHpActual() <= 0.0);

                if ($rompioEscudo) {
                    $resultado = new ResultadoDanio(
                        danoEscudo: $destruidoEsp + $destruidoFis,
                        rompioEscudo: true,
                    );
                    $objetivo->dispararEscudoRoto($portador, $resultado, $battle);
                    $portador->dispararEscudoRoto($objetivo, $resultado, $battle);
                }

                $battle->agregarLog("{$objetivo->nombre()} pierde escudo por {$this->clave}");

                continue;
            }

            $cantidad = $objetivo->pokemon()->battleStats()->hp * $this->fraccion;
            if ($cantidad <= 0.0) {
                continue;
            }

            $objetivo->recibirDaño($cantidad, $this->especial, $this->vaDirectoAHp ? 1.0 : 0.0);
            $battle->agregarLog("{$objetivo->nombre()} sufre ".round($cantidad)." de daño por {$this->clave}");

            if ($objetivo->hpActual() <= 0.0) {
                $battle->agregarLog("¡{$objetivo->nombre()} se ha debilitado por {$this->clave}!");
            }
        }
    }

    /**
     * @return list<Combatiente>
     */
    private function objetivos(Combatiente $portador, AgregadoBatalla $battle): array
    {
        $equipoPropio = $this->equipoDe($battle, $portador);
        $equipoRival = $this->equipoRivalDe($battle, $portador);
        if ($equipoPropio === null || $equipoRival === null) {
            return [];
        }

        return match ($this->objetivo) {
            ObjetivoEfecto::PROPIO => [$portador],
            ObjetivoEfecto::EQUIPO => $equipoPropio->combatientesCollection()->vivos()->all(),
            ObjetivoEfecto::ENEMIGOS => $equipoRival->combatientesCollection()->vivos()->all(),
            ObjetivoEfecto::ENEMIGOS_DORMIDOS => array_values(array_filter(
                $equipoRival->combatientesCollection()->vivos()->all(),
                static fn (Combatiente $c): bool => $c->estado() === EstadoPokemon::SLEEP,
            )),
            ObjetivoEfecto::ESCUDOS_ENEMIGOS => $equipoRival->combatientesCollection()->vivos()->all(),
        };
    }

    private function equipoProtegido(Combatiente $objetivo, AgregadoBatalla $battle): bool
    {
        $equipo = $this->equipoDe($battle, $objetivo);
        if ($equipo === null) {
            return false;
        }

        foreach ($equipo->combatientesCollection()->vivos() as $aliado) {
            if ($aliado->bloqueaDanioIndirecto()) {
                return true;
            }
        }

        return false;
    }
}
