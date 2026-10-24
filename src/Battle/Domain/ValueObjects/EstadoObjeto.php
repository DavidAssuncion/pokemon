<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\Catalog\CatalogoObjetos;

/**
 * Estado del objeto equipado de un combatiente.
 *
 * Encapsula la clave, las cargas, el último objeto consumido (Reciclaje) y la
 * ronda hasta la que el objeto está bloqueado. `Combatiente` delega aquí para
 * no mezclar reglas de inventario con HP, estados o turnos.
 */
final class EstadoObjeto
{
    private string $clave = '';

    /** -1 = pasivo; 0 = consumido/destruido; >0 = cargas restantes. */
    private int $cargas = 0;

    private string $consumido = '';

    private int $bloqueadoHastaRonda = 0;

    private string $movimientoBloqueado = '';

    public function clave(): string
    {
        return $this->clave;
    }

    public function cargas(): int
    {
        return $this->cargas;
    }

    public function consumido(): string
    {
        return $this->consumido;
    }

    public function bloqueadoHastaRonda(): int
    {
        return $this->bloqueadoHastaRonda;
    }

    public function movimientoBloqueado(): string
    {
        return $this->movimientoBloqueado;
    }

    public function bloquearMovimiento(string $nombre): void
    {
        $this->movimientoBloqueado = $nombre;
    }

    public function asignar(string $clave): void
    {
        $this->clave = $clave;
        $this->cargas = $clave !== '' ? CatalogoObjetos::cargasDe($clave) : 0;
        $this->consumido = '';
        $this->bloqueadoHastaRonda = 0;
        $this->movimientoBloqueado = '';
    }

    public function restaurar(
        string $clave,
        int $cargas,
        string $consumido,
        int $bloqueadoHastaRonda,
        string $movimientoBloqueado = '',
    ): void {
        $this->clave = $clave;
        $this->cargas = $cargas;
        $this->consumido = $consumido;
        $this->bloqueadoHastaRonda = max(0, $bloqueadoHastaRonda);
        $this->movimientoBloqueado = $movimientoBloqueado;
    }

    public function activo(int $rondaActual, bool $embargado): bool
    {
        return $this->clave !== ''
            && $this->cargas !== 0
            && ! $this->bloqueado($rondaActual, $embargado);
    }

    public function bloqueado(int $rondaActual, bool $embargado): bool
    {
        return $embargado || $this->bloqueadoHastaRonda > $rondaActual;
    }

    /**
     * Devuelve true si el objeto se agotó.
     */
    public function consumir(): bool
    {
        if ($this->clave === '' || $this->cargas <= 0) {
            return false;
        }

        $this->cargas--;
        if ($this->cargas > 0) {
            return false;
        }

        $this->consumido = $this->clave;
        $this->clave = '';

        return true;
    }

    public function bloquear(int $rondaActual, int $turnos): void
    {
        $this->bloqueadoHastaRonda = max(
            $this->bloqueadoHastaRonda,
            $rondaActual + max(1, $turnos),
        );
    }

    public function destruir(): string
    {
        $destruido = $this->clave;
        $this->clave = '';
        $this->cargas = 0;
        $this->consumido = '';
        $this->movimientoBloqueado = '';

        return $destruido;
    }

    public function robarDe(self $otro): bool
    {
        if ($otro->clave === '' || $this->clave !== '') {
            return false;
        }

        $this->clave = $otro->clave;
        $this->cargas = $otro->cargas;
        $this->consumido = '';
        $this->bloqueadoHastaRonda = 0;
        $this->movimientoBloqueado = '';

        $otro->clave = '';
        $otro->cargas = 0;
        $otro->consumido = '';
        $otro->movimientoBloqueado = '';

        return true;
    }

    public function intercambiarCon(self $otro): bool
    {
        if ($this->clave === '' && $otro->clave === '') {
            return false;
        }

        [$clave, $cargas, $consumido, $bloqueado] = [
            $this->clave,
            $this->cargas,
            $this->consumido,
            $this->bloqueadoHastaRonda,
        ];

        $this->clave = $otro->clave;
        $this->cargas = $otro->cargas;
        $this->consumido = $otro->consumido;
        $this->bloqueadoHastaRonda = $otro->bloqueadoHastaRonda;
        $this->movimientoBloqueado = '';

        $otro->clave = $clave;
        $otro->cargas = $cargas;
        $otro->consumido = $consumido;
        $otro->bloqueadoHastaRonda = $bloqueado;
        $otro->movimientoBloqueado = '';

        return true;
    }

    public function reciclar(): bool
    {
        $clave = $this->clave !== '' ? $this->clave : $this->consumido;
        if ($clave === '' || $this->cargas !== 0) {
            return false;
        }

        $this->clave = $clave;
        $this->cargas = max(1, CatalogoObjetos::cargasDe($clave));
        $this->consumido = '';
        $this->movimientoBloqueado = '';

        return true;
    }
}
