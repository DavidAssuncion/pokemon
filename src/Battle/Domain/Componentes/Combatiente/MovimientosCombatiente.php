<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\ClaveItem;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Battle\Domain\ValueObjects\EstadoMovimientos;
use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Cargas, usos y elegibilidad de movimientos.
 */
final class MovimientosCombatiente
{
    public function __construct(
        private EstadoMovimientos $movimientos = new EstadoMovimientos(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function desdeArray(array $data): self
    {
        $movimientos = new EstadoMovimientos();
        $movimientos->restaurar(
            MapaConteo::desdeArray(is_array($data['cargasMovimiento'] ?? null) ? $data['cargasMovimiento'] : []),
            MapaConteo::desdeArray(is_array($data['usosMovimiento'] ?? null) ? $data['usosMovimiento'] : []),
        );

        return new self($movimientos);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(): array
    {
        return [
            'cargasMovimiento' => $this->movimientos->cargas()->toArray(),
            'usosMovimiento' => $this->movimientos->usos()->toArray(),
        ];
    }

    public function __clone(): void
    {
        $this->movimientos = clone $this->movimientos;
    }

    /**
     * @param  list<MovimientoBatalla>  $movimientos
     */
    public function avanzarCargas(array $movimientos): void
    {
        foreach ($movimientos as $movimiento) {
            $this->movimientos->avanzarCarga($movimiento);
        }
    }

    public function carga(MovimientoBatalla $movimiento): int
    {
        return $this->movimientos->carga($movimiento);
    }

    public function usosRestantes(MovimientoBatalla $movimiento): int
    {
        return $this->movimientos->usosRestantes($movimiento);
    }

    public function motivoMovimientoNoDisponible(
        MovimientoBatalla $movimiento,
        bool $silencio,
        bool $itemActivo,
        string $item,
        string $movimientoBloqueado,
    ): ?string {
        if ($silencio && $movimiento->esEstado()) {
            return 'silencio';
        }

        $cargaMaxima = $movimiento->perfil->cargaMaxima;
        if ($cargaMaxima > 0 && $this->carga($movimiento) < $cargaMaxima) {
            return 'cargando';
        }

        if ($this->usosRestantes($movimiento) === 0) {
            return 'sin_usos';
        }

        if ($itemActivo
            && in_array($item, [ClaveItem::CINTA_ELECCION->value, ClaveItem::GAFAS_ELECCION->value], true)
            && $movimientoBloqueado !== ''
            && $movimientoBloqueado !== $movimiento->nombre) {
            return 'objeto_eleccion';
        }

        return null;
    }

    public function consumir(
        MovimientoBatalla $movimiento,
        bool $itemActivo,
        string $item,
        ObjetoCombatiente $objeto,
        CondicionesCombatiente $condiciones,
        RecursosCombatiente $recursos,
    ): void {
        $this->movimientos->consumir($movimiento);

        if ($itemActivo
            && in_array($item, [ClaveItem::CINTA_ELECCION->value, ClaveItem::GAFAS_ELECCION->value], true)) {
            $objeto->bloquearMovimiento($movimiento->nombre);
        }

        if ($movimiento->perfil->recarga) {
            $condiciones->quitar(ClaveCondicion::RECARGA);
            $condiciones->agregar(new CondicionVolatil(ClaveCondicion::RECARGA, 1));
            $recursos->romperCombo();
        }
    }

    public function cancelarCargas(): void
    {
        $this->movimientos->cancelarCargas();
    }

    public function cargas(): MapaConteo
    {
        return $this->movimientos->cargas();
    }

    public function usos(): MapaConteo
    {
        return $this->movimientos->usos();
    }

    public function reiniciar(): void
    {
        $this->movimientos->reiniciar();
    }
}
