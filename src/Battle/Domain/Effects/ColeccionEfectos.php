<?php

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\AgregadoBatalla;

class ColeccionEfectos
{
    /** @var InterfazEfecto[] */
    private array $effects = [];

    public function add(InterfazEfecto $effect): void
    {
        $this->effects[] = $effect;
    }

    public function all(): array
    {
        return $this->effects;
    }

    public function unicos(): array
    {
        return array_values(array_filter(
            $this->effects,
            fn(InterfazEfecto $e) => $e->esUnico()
        ));
    }

    public function find(string $clave): ?InterfazEfecto
    {
        foreach ($this->effects as $e) {
            if ($e->obtenerClave() === $clave) {
                return $e;
            }
        }
        return null;
    }

    public function count(): int
    {
        return count($this->effects);
    }

    public function isEmpty(): bool
    {
        return empty($this->effects);
    }

    // ─── Event triggers ──────────────────────────────────────

    public function triggerBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onBattleStart($portador, $battle);
        }
    }

    public function triggerRoundStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onRoundStart($portador, $battle);
        }
    }

    public function triggerRoundEnd(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onRoundEnd($portador, $battle);
        }
    }

    public function dispararDanioInfligido(Combatiente $portador, Combatiente $target, float $daño, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onDamageDealt($portador, $target, $daño, $battle);
        }
    }

    public function dispararDanioRecibido(Combatiente $portador, float $daño, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onDamageReceived($portador, $daño, $battle);
        }
    }

    public function triggerHealed(Combatiente $portador, float $cantidad): void
    {
        foreach ($this->effects as $e) {
            $e->onHealed($portador, $cantidad);
        }
    }

    public function dispararDebilitado(Combatiente $portador): void
    {
        foreach ($this->effects as $e) {
            $e->onFainted($portador);
        }
    }

    public function dispararInicioTurno(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onTurnStart($portador, $battle);
        }
    }

    public function dispararFinTurno(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onTurnEnd($portador, $battle);
        }
    }
}
