<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;

/**
 * Comealmas: cuando un rival cae, el portador recupera un 15 % de sus escudos;
 * si el portador fue el último en golpearlo, recupera un 30 %.
 *
 * La detección se hace en el cierre final de la ronda, después de estados,
 * clima y notificaciones de muerte, comparando los rivales vivos con la
 * instantánea del inicio de la batalla.
 */
class EfectoComealmas implements InterfazEfecto
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    /** @var array<string, true> */
    private array $vivos = [];

    private bool $inicializado = false;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $this->vivos = $this->instantaneaRivales($battle, $portador);
        $this->inicializado = true;
    }

    public function onRoundEndFinal(Combatiente $portador, AgregadoBatalla $battle): void
    {
        if (! $portador->estaVivo()) {
            return;
        }

        if (! $this->inicializado) {
            $this->onBattleStart($portador, $battle);
        }

        $rival = $this->equipoRivalDe($battle, $portador);
        if ($rival === null) {
            return;
        }

        $ahora = $this->instantaneaRivales($battle, $portador);
        foreach (array_diff_key($this->vivos, $ahora) as $id => $_) {
            $muerto = $rival->findCombatantById($id);
            if ($muerto === null || $muerto->esInvocacion()) {
                // Una invocación retirada o temporal no cuenta como KO.
                continue;
            }

            $porcentaje = $muerto->ultimoAtacanteId() === $portador->id() ? 30.0 : 15.0;
            $portador->curarBarreras($porcentaje);
            $battle->agregarLog(
                "{$portador->nombre()} recupera {$porcentaje}% de escudos por Comealmas",
            );
        }

        $this->vivos = $ahora;
    }

    /**
     * @return array<string, true>
     */
    private function instantaneaRivales(AgregadoBatalla $battle, Combatiente $portador): array
    {
        $rival = $this->equipoRivalDe($battle, $portador);
        if ($rival === null) {
            return [];
        }

        $vivos = [];
        foreach ($rival->combatientesCollection()->vivos() as $combatiente) {
            $vivos[$combatiente->id()] = true;
        }

        return $vivos;
    }
}
