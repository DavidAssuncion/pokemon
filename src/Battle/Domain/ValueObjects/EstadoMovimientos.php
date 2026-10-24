<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\MovimientoBatalla;
use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Cargas y usos limitados por nombre de movimiento.
 */
final class EstadoMovimientos
{
    /** @var array<string, int> */
    private array $cargas = [];

    /** @var array<string, int> */
    private array $usos = [];

    public function avanzarCarga(MovimientoBatalla $movimiento): void
    {
        $maximo = $movimiento->perfil->cargaMaxima;
        if ($maximo <= 0) {
            return;
        }

        $actual = $this->cargas[$movimiento->nombre] ?? 0;
        $this->cargas[$movimiento->nombre] = min(
            $maximo,
            $actual + max(1, $movimiento->perfil->cargaPorRonda),
        );
    }

    public function carga(MovimientoBatalla $movimiento): int
    {
        return $this->cargas[$movimiento->nombre] ?? 0;
    }

    public function usosRestantes(MovimientoBatalla $movimiento): int
    {
        $maximo = $movimiento->perfil->usosMaximos;
        if ($maximo <= 0) {
            return -1;
        }

        return max(0, $maximo - ($this->usos[$movimiento->nombre] ?? 0));
    }

    public function consumir(MovimientoBatalla $movimiento): void
    {
        if ($movimiento->perfil->cargaMaxima > 0) {
            $this->cargas[$movimiento->nombre] = 0;
        }

        if ($movimiento->perfil->usosMaximos > 0) {
            $this->usos[$movimiento->nombre] = min(
                $movimiento->perfil->usosMaximos,
                ($this->usos[$movimiento->nombre] ?? 0) + 1,
            );
        }
    }

    public function cancelarCargas(): void
    {
        $this->cargas = [];
    }

    public function cargas(): MapaConteo
    {
        return MapaConteo::desdeArray($this->cargas);
    }

    public function usos(): MapaConteo
    {
        return MapaConteo::desdeArray($this->usos);
    }

    public function restaurar(MapaConteo $cargas, MapaConteo $usos): void
    {
        $this->cargas = $this->normalizar($cargas->toArray());
        $this->usos = $this->normalizar($usos->toArray());
    }

    public function reiniciar(): void
    {
        $this->cargas = [];
        $this->usos = [];
    }

    /**
     * @param  array<mixed, mixed>  $valores
     * @return array<string, int>
     */
    private function normalizar(array $valores): array
    {
        $resultado = [];
        foreach ($valores as $nombre => $valor) {
            if (is_string($nombre)) {
                $resultado[$nombre] = max(0, (int) $valor);
            }
        }

        return $resultado;
    }
}
