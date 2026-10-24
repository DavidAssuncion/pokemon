<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlContacto;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/**
 * Estado (o bajada de stat) con probabilidad al recibir un movimiento de
 * contacto. Cubre Electricidad Estática, Punto Tóxico, Cuerpo Llama,
 * Baba Tóxica y Enredaderas.
 */
class EfectoContactoEstado implements InterfazEfecto, ReaccionaAlContacto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly EstadoPokemon $estado,
        private readonly float $probabilidad,
        private readonly ?StatClave $statReducido = null,
        private readonly int $etapasStat = 0,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function alRecibirContacto(Combatiente $portador, Combatiente $atacante, AgregadoBatalla $battle): void
    {
        if ($atacante->tieneEfecto('remoto')) {
            return;
        }

        if (! $atacante->estaVivo() || mt_rand() / mt_getrandmax() >= $this->probabilidad) {
            return;
        }

        if ($this->statReducido !== null && $this->etapasStat !== 0) {
            $cambio = CambioStat::desdeEtapas($this->statReducido, $this->etapasStat);
            $atacante->setMultiplicadores($cambio->aplicadoTemporalEn($atacante->multiplicadores()));
        }

        if ($this->estado === EstadoPokemon::NONE) {
            return;
        }

        if ($atacante->estado() !== EstadoPokemon::NONE) {
            return;
        }

        if (! $atacante->effects()->permiteEstado($atacante, $this->estado, $battle)) {
            return;
        }

        $atacante->setEstado($this->estado);
        $atacante->setTurnosEstado(match ($this->estado) {
            EstadoPokemon::SLEEP, EstadoPokemon::CONFUSION => mt_rand(2, 4),
            default => 0,
        });

        if ($this->estado === EstadoPokemon::POISON || $this->estado === EstadoPokemon::BAD_POISON) {
            $atacante->aplicarCargaVeneno(1);
        }

        $battle->agregarLog("{$atacante->nombre()} sufre {$this->estado->label()} por contacto");
    }
}
