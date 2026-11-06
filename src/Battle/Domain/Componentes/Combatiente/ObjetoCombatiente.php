<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\Catalog\CatalogoObjetos;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Battle\Domain\ValueObjects\EstadoObjeto;

/**
 * Objeto equipado, cargas, bloqueo, robo e intercambio.
 */
final class ObjetoCombatiente
{
    public function __construct(
        private EstadoObjeto $objeto = new EstadoObjeto(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function desdeArray(array $data): self
    {
        $item = (string) ($data['item'] ?? '');
        $objeto = new EstadoObjeto();
        $objeto->restaurar(
            clave: $item,
            cargas: (int) ($data['itemCargas'] ?? ($item !== '' ? CatalogoObjetos::cargasDe($item) : 0)),
            consumido: (string) ($data['itemConsumido'] ?? ''),
            bloqueadoHastaRonda: (int) ($data['itemBloqueadoHastaRonda'] ?? 0),
            movimientoBloqueado: (string) ($data['movimientoBloqueado'] ?? ''),
        );

        return new self($objeto);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(): array
    {
        return [
            'item' => $this->objeto->clave(),
            'itemCargas' => $this->objeto->cargas(),
            'itemConsumido' => $this->objeto->consumido(),
            'itemBloqueadoHastaRonda' => $this->objeto->bloqueadoHastaRonda(),
            'movimientoBloqueado' => $this->objeto->movimientoBloqueado(),
        ];
    }

    public function __clone(): void
    {
        $this->objeto = clone $this->objeto;
    }

    public function clave(): string
    {
        return $this->objeto->clave();
    }

    public function cargas(): int
    {
        return $this->objeto->cargas();
    }

    public function consumido(): string
    {
        return $this->objeto->consumido();
    }

    public function movimientoBloqueado(): string
    {
        return $this->objeto->movimientoBloqueado();
    }

    public function asignar(string $item): void
    {
        $this->objeto->asignar($item);
    }

    public function consumir(): bool
    {
        return $this->objeto->consumir();
    }

    public function bloquearMovimiento(string $nombre): void
    {
        $this->objeto->bloquearMovimiento($nombre);
    }

    public function activo(TurnoCombatiente $turno, CondicionesCombatiente $condiciones): bool
    {
        return $this->objeto->activo(
            $turno->rondaActual(),
            $condiciones->tiene(ClaveCondicion::EMBARGO),
        );
    }

    public function bloqueado(TurnoCombatiente $turno, CondicionesCombatiente $condiciones): bool
    {
        return $this->objeto->bloqueado(
            $turno->rondaActual(),
            $condiciones->tiene(ClaveCondicion::EMBARGO),
        );
    }

    public function bloquear(int $turnos, TurnoCombatiente $turno, CondicionesCombatiente $condiciones): void
    {
        $turnos = max(1, $turnos);
        $this->objeto->bloquear($turno->rondaActual(), $turnos);

        if (! $condiciones->tiene(ClaveCondicion::EMBARGO)) {
            $condiciones->agregar(new CondicionVolatil(ClaveCondicion::EMBARGO, $turnos));
        }
    }

    public function destruir(): string
    {
        return $this->objeto->destruir();
    }

    public function robarDe(self $otro): bool
    {
        return $this->objeto->robarDe($otro->objeto);
    }

    public function intercambiarCon(self $otro): bool
    {
        return $this->objeto->intercambiarCon($otro->objeto);
    }

    public function reciclar(): bool
    {
        return $this->objeto->reciclar();
    }

    public function reiniciar(): void
    {
        $this->objeto = new EstadoObjeto();
    }
}
