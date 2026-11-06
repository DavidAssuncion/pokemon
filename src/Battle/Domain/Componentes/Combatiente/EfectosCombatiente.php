<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\Effects\Capacidades\IgnoraHabilidad;
use Src\Battle\Domain\Effects\ColeccionEfectos;
use Src\Battle\Domain\Effects\InterfazEfecto;
use Src\Battle\Domain\Enums\ClaveCondicion;

/**
 * Colección de efectos activos del combatiente.
 */
final class EfectosCombatiente
{
    public function __construct(
        private ColeccionEfectos $effects = new ColeccionEfectos(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function desdeArray(array $data): self
    {
        return new self(SerializadorCombatiente::deserializarEfectos($data['effects'] ?? null));
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(): array
    {
        return ['effects' => serialize($this->effects)];
    }

    public function __clone(): void
    {
        $this->effects = clone $this->effects;
    }

    public function coleccion(): ColeccionEfectos
    {
        return $this->effects;
    }

    public function tieneEfecto(string $clave): bool
    {
        return $this->effects->find($clave) !== null;
    }

    public function obtenerPorcentajeDanioDirecto(): float
    {
        $pct = 0.0;
        foreach ($this->effects->all() as $effect) {
            $pct += $effect->obtenerPorcentajeDanioDirecto();
        }

        return min($pct, 1.0);
    }

    public function ignoraHabilidadRival(CondicionesCombatiente $condiciones): bool
    {
        if ($condiciones->tiene(ClaveCondicion::IGNORA_HABILIDAD)) {
            return true;
        }

        foreach ($this->effects->all() as $efecto) {
            if ($efecto instanceof IgnoraHabilidad && $efecto->ignoraHabilidadRival()) {
                return true;
            }
        }

        return false;
    }

    public function multiplicadorEfectoEstado(): float
    {
        return $this->tieneEfecto('meditacion') ? 2.0 : 1.0;
    }

    /**
     * @param  list<InterfazEfecto>  $efectos
     */
    public function reemplazar(array $efectos): void
    {
        $this->effects->reemplazar($efectos);
    }

    /**
     * @return list<InterfazEfecto>
     */
    public function all(): array
    {
        return $this->effects->all();
    }

    public function reiniciar(): void
    {
        $this->effects = new ColeccionEfectos();
    }
}
