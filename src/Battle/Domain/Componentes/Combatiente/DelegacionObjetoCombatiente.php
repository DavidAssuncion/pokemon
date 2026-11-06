<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\Combatiente;

trait DelegacionObjetoCombatiente
{
    public function item(): string
    {
        return $this->objeto->clave();
    }

    public function itemCargas(): int
    {
        return $this->objeto->cargas();
    }

    public function itemConsumido(): string
    {
        return $this->objeto->consumido();
    }

    public function movimientoBloqueado(): string
    {
        return $this->objeto->movimientoBloqueado();
    }

    public function itemBloqueado(): bool
    {
        return $this->objeto->bloqueado($this->turno, $this->condiciones);
    }

    public function itemActivo(): bool
    {
        return $this->objeto->activo($this->turno, $this->condiciones);
    }

    public function consumirCargaObjeto(): bool
    {
        return $this->objeto->consumir();
    }

    public function bloquearObjeto(int $turnos = 5): void
    {
        $this->objeto->bloquear($turnos, $this->turno, $this->condiciones);
    }

    public function destruirObjeto(): string
    {
        return $this->objeto->destruir();
    }

    public function robarObjetoDe(Combatiente $otro): bool
    {
        return $this->objeto->robarDe($otro->objeto);
    }

    public function intercambiarObjetoCon(Combatiente $otro): bool
    {
        return $this->objeto->intercambiarCon($otro->objeto);
    }

    public function reciclarObjeto(): bool
    {
        return $this->objeto->reciclar();
    }
}
