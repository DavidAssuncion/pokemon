<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Battle\Domain\ValueObjects\JefeEstado;

/**
 * Estado de jefe: fases y puntos débiles.
 */
final class JefeCombatiente
{
    public function __construct(
        private ?JefeEstado $jefe = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function desdeArray(array $data): self
    {
        return new self(SerializadorCombatiente::deserializarJefe($data['jefe'] ?? null));
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(): array
    {
        return ['jefe' => serialize($this->jefe)];
    }

    public function __clone(): void
    {
        if ($this->jefe !== null) {
            $this->jefe = clone $this->jefe;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $fases
     * @param  list<array<string, mixed>>  $partes
     */
    public function configurar(array $fases, array $partes, float $hpMax): void
    {
        $this->jefe = new JefeEstado(
            fases: $fases,
            partes: $partes,
            hpMax: max(1.0, $hpMax),
        );
    }

    public function esJefe(): bool
    {
        return $this->jefe !== null;
    }

    public function jefe(): ?JefeEstado
    {
        return $this->jefe;
    }

    public function multiplicadorDanioInfligido(): float
    {
        return $this->jefe?->multiplicadorDanioInfligido() ?? 1.0;
    }

    public function multiplicadorVelocidad(): float
    {
        return $this->jefe?->multiplicadorVelocidad() ?? 1.0;
    }

    /**
     * @param  callable(string): void  $log
     */
    public function procesarDanio(
        float $dano,
        float $hpActual,
        float $hpMax,
        string $nombre,
        callable $log,
        CondicionesCombatiente $condiciones,
    ): void {
        if ($this->jefe === null || $dano <= 0.0) {
            return;
        }

        foreach ($this->jefe->procesarDanio($hpActual, $hpMax) as $evento) {
            $log("¡{$nombre} entra en {$evento->nombre}! (+".
                (int) round(($evento->bonificadorDano - 1.0) * 100).'% daño)');
        }

        if ($this->jefe->daniarParte($dano)) {
            $condiciones->reemplazar(new CondicionVolatil(
                clave: ClaveCondicion::VULNERABLE,
                turnos: 3,
                multiplicadorDanoRecibido: 1.25,
            ));
            $log("¡Se rompe una parte de {$nombre}! Queda vulnerable");
        }
    }

    public function reiniciar(): void
    {
        $this->jefe = null;
    }
}
