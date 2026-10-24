<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlContacto;
use Src\Battle\Domain\Enums\EstadoPokemon;

/**
 * Estado aleatorio con probabilidad al recibir contacto (Efecto Espora:
 * 30 % de dormir, paralizar o envenenar).
 */
class EfectoContactoEstadoAleatorio implements InterfazEfecto, ReaccionaAlContacto
{
    use ComportamientosPorDefecto;

    /** @var list<EstadoPokemon> */
    private readonly array $estados;

    public function __construct(
        private readonly string $clave,
        private readonly float $probabilidad,
        EstadoPokemon ...$estados,
    ) {
        $this->estados = array_values($estados);
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

        if (! $atacante->estaVivo() || $this->estados === []) {
            return;
        }

        if (mt_rand() / mt_getrandmax() >= $this->probabilidad) {
            return;
        }

        $estado = $this->estados[mt_rand(0, count($this->estados) - 1)];

        if ($atacante->estado() !== EstadoPokemon::NONE) {
            return;
        }

        if (! $atacante->effects()->permiteEstado($atacante, $estado, $battle)) {
            return;
        }

        $atacante->setEstado($estado);
        $atacante->setTurnosEstado(match ($estado) {
            EstadoPokemon::SLEEP, EstadoPokemon::CONFUSION => mt_rand(2, 4),
            default => 0,
        });

        if ($estado === EstadoPokemon::POISON || $estado === EstadoPokemon::BAD_POISON) {
            $atacante->aplicarCargaVeneno(1);
        }

        $battle->agregarLog("{$atacante->nombre()} sufre {$estado->label()} por contacto");
    }
}
