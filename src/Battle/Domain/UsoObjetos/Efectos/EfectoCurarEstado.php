<?php

declare(strict_types=1);

namespace Src\Battle\Domain\UsoObjetos\Efectos;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\ObjetivosItem;
use Src\Battle\Domain\UsoObjetos\EfectoItem;
use Src\Battle\Domain\UsoObjetos\ResultadoEfectoItem;

/**
 * Cura uno o varios estados alterados concretos.
 *
 * Acepta estados mayores y `CONFUSION`. La confusión vive además como
 * condición volátil para poder coexistir con quemadura o veneno.
 */
final readonly class EfectoCurarEstado implements EfectoItem
{
    /** @var list<EstadoPokemon> */
    private array $estados;

    public function __construct(EstadoPokemon ...$estados)
    {
        if ($estados === []) {
            throw new \InvalidArgumentException('Debe indicarse al menos un estado a curar.');
        }

        $this->estados = array_values($estados);
    }

    public function clave(): string
    {
        return 'curar_estado';
    }

    public function objetivos(): ObjetivosItem
    {
        return ObjetivosItem::ALIADO_VIVO;
    }

    public function validar(Combatiente $objetivo): ?string
    {
        if (! $objetivo->estaVivo()) {
            return "{$objetivo->nombre()} está debilitado.";
        }

        if (! $this->puedeCurar($objetivo)) {
            return "{$objetivo->nombre()} no sufre un estado que este objeto cure.";
        }

        return null;
    }

    public function aplicar(Combatiente $objetivo, Combatiente $actor): ResultadoEfectoItem
    {
        $estadoMayor = $objetivo->estadoMayor();
        $curaEstadoMayor = $estadoMayor !== EstadoPokemon::NONE && $this->incluyeEstadoMayor($estadoMayor);
        $curaConfusion = in_array(EstadoPokemon::CONFUSION, $this->estados, true)
            && $objetivo->tieneCondicion(ClaveCondicion::CONFUSION);

        if ($curaEstadoMayor) {
            $objetivo->setEstado(EstadoPokemon::NONE);
            $objetivo->setContadorVenenoGrave(0);
        }

        if ($curaConfusion) {
            $objetivo->quitarCondicion(ClaveCondicion::CONFUSION);
        }

        $nombres = array_map(
            static fn (EstadoPokemon $estado): string => $estado->label(),
            $this->estados,
        );

        return new ResultadoEfectoItem(
            texto: "{$objetivo->nombre()} se recuperó de ".implode(' / ', $nombres).'.',
        );
    }

    private function puedeCurar(Combatiente $objetivo): bool
    {
        $estadoMayor = $objetivo->estadoMayor();
        if ($estadoMayor !== EstadoPokemon::NONE && $this->incluyeEstadoMayor($estadoMayor)) {
            return true;
        }

        return in_array(EstadoPokemon::CONFUSION, $this->estados, true)
            && $objetivo->tieneCondicion(ClaveCondicion::CONFUSION);
    }

    private function incluyeEstadoMayor(EstadoPokemon $estado): bool
    {
        if (in_array($estado, $this->estados, true)) {
            return true;
        }

        // Cualquier cura de veneno cubre también el veneno grave.
        return $estado === EstadoPokemon::BAD_POISON
            && in_array(EstadoPokemon::POISON, $this->estados, true);
    }
}
