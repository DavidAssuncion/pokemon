<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\Enums\StatClave;
use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Modificadores multiplicativos de estadísticas de un combatiente.
 *
 * Tiene DOS capas dentro del MISMO objeto:
 * - base: etapas permanentes, habilidades y objetos.
 * - temporal: stacks de buffs/debuffs, con duración compartida.
 *
 * El multiplicador final es base × temporal y se limita a 0.25–4.0 (25 %–400 %).
 */
final class MultiplicadoresStats
{
    public const float DEFAULT_MIN = 0.25;

    public const float DEFAULT_MAX = 4.0;

    public const int TURNOS_TEMPORALES = 5;

    /** @var array<string, float> */
    private array $factores;

    /** @var array<string, float> */
    private array $temporales;

    private int $turnosTemporales;

    private float $limiteInferior;

    private float $limiteSuperior;

    /**
     * @param  array<string, float>  $factores  Claves StatClave->value.
     * @param  array<string, float>  $temporales  Claves StatClave->value.
     */
    private function __construct(
        array $factores,
        float $limiteInferior = self::DEFAULT_MIN,
        float $limiteSuperior = self::DEFAULT_MAX,
        array $temporales = [],
        int $turnosTemporales = 0,
    ) {
        $this->limiteInferior = $limiteInferior;
        $this->limiteSuperior = $limiteSuperior;
        $this->turnosTemporales = max(0, $turnosTemporales);
        $this->factores = [];
        $this->temporales = [];

        foreach ($factores as $clave => $factor) {
            StatClave::from($clave);
            $this->factores[$clave] = $this->clampear($factor);
        }

        foreach ($temporales as $clave => $factor) {
            if (StatClave::tryFrom($clave) !== null) {
                $this->temporales[$clave] = $factor;
            }
        }
    }

    // ─── Fábricas ──────────────────────────────────────────────

    public static function factorDesdeStages(int $n): float
    {
        return $n >= 0
            ? (2 + $n) / 2
            : 2 / (2 - $n);
    }

    public static function vacia(): self
    {
        return new self([
            StatClave::ATAQUE->value => 1.0,
            StatClave::DEFENSA->value => 1.0,
            StatClave::ATAQUE_ESPECIAL->value => 1.0,
            StatClave::DEFENSA_ESPECIAL->value => 1.0,
            StatClave::VELOCIDAD->value => 1.0,
            StatClave::PRECISION->value => 1.0,
            StatClave::EVASION->value => 1.0,
        ]);
    }

    /**
     * Acepta el formato actual {base, temporales, turnos} y el legacy de solo
     * factores planos.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function desdeSerializado(array $datos): self
    {
        if (isset($datos['base']) && is_array($datos['base'])) {
            $base = [];
            foreach ($datos['base'] as $clave => $factor) {
                if (StatClave::tryFrom((string) $clave) !== null) {
                    $base[(string) $clave] = (float) $factor;
                }
            }

            $temporales = [];
            if (isset($datos['temporales']) && is_array($datos['temporales'])) {
                foreach ($datos['temporales'] as $clave => $factor) {
                    if (StatClave::tryFrom((string) $clave) !== null) {
                        $temporales[(string) $clave] = (float) $factor;
                    }
                }
            }

            return new self($base, self::DEFAULT_MIN, self::DEFAULT_MAX, $temporales, (int) ($datos['turnos'] ?? 0));
        }

        $base = [];
        foreach ($datos as $clave => $factor) {
            if (StatClave::tryFrom($clave) !== null) {
                $base[$clave] = (float) $factor;
            }
        }

        return new self($base);
    }

    /**
     * @param  array<string, int>  $etapasInt
     */
    public static function desdeEtapas(array $etapasInt): self
    {
        $factores = [];
        foreach ($etapasInt as $clave => $etapa) {
            if (StatClave::tryFrom($clave) === null) {
                continue;
            }
            $factores[$clave] = self::factorDesdeStages($etapa);
        }

        return new self($factores);
    }

    // ─── Operaciones inmutables (capa base) ───────────────────

    public function aplicarFactor(StatClave $stat, float $factor): self
    {
        $nuevos = $this->factores;
        $clave = $stat->value;
        $actual = $nuevos[$clave] ?? 1.0;
        $nuevos[$clave] = $this->clampear($actual * $factor);

        return new self($nuevos, $this->limiteInferior, $this->limiteSuperior, $this->temporales, $this->turnosTemporales);
    }

    public function aplicarPorcentaje(StatClave $stat, float $porcentaje): self
    {
        return $this->aplicarFactor($stat, 1 + $porcentaje / 100);
    }

    // ─── Operaciones inmutables (capa temporal) ───────────────

    /**
     * Añade un stack temporal y reinicia la duración compartida a 5 turnos.
     */
    public function aplicarTemporal(StatClave $stat, float $factor): self
    {
        $temporales = $this->temporales;
        $clave = $stat->value;
        $temporales[$clave] = ($temporales[$clave] ?? 1.0) * $factor;

        return new self(
            $this->factores,
            $this->limiteInferior,
            $this->limiteSuperior,
            $temporales,
            self::TURNOS_TEMPORALES,
        );
    }

    public function aplicarPorcentajeTemporal(StatClave $stat, float $porcentaje): self
    {
        return $this->aplicarTemporal($stat, 1 + $porcentaje / 100);
    }

    /**
     * Descuenta un turno. Si llega a 0, elimina todos los temporales.
     */
    public function avanzarTemporales(): self
    {
        if ($this->turnosTemporales <= 0) {
            return $this;
        }

        $turnos = $this->turnosTemporales - 1;

        return new self(
            $this->factores,
            $this->limiteInferior,
            $this->limiteSuperior,
            $turnos > 0 ? $this->temporales : [],
            $turnos,
        );
    }

    public function reiniciarTemporales(): self
    {
        return new self($this->factores, $this->limiteInferior, $this->limiteSuperior);
    }

    public function combinar(self $otros): self
    {
        $nuevos = $this->factores;
        foreach ($otros->factores as $clave => $factor) {
            $actual = $nuevos[$clave] ?? 1.0;
            $nuevos[$clave] = $this->clampear($actual * $factor);
        }

        $temporales = $this->temporales;
        foreach ($otros->temporales as $clave => $factor) {
            $temporales[$clave] = ($temporales[$clave] ?? 1.0) * $factor;
        }

        return new self(
            $nuevos,
            $this->limiteInferior,
            $this->limiteSuperior,
            $temporales,
            max($this->turnosTemporales, $otros->turnosTemporales),
        );
    }

    public function conLimites(float $min, float $max): self
    {
        $nuevos = [];
        foreach ($this->factores as $clave => $factor) {
            $nuevos[$clave] = max($min, min($max, $factor));
        }

        return new self($nuevos, $min, $max, $this->temporales, $this->turnosTemporales);
    }

    // ─── Consultas ─────────────────────────────────────────────

    public function obtenerMultiplicador(StatClave $stat): float
    {
        $base = $this->factores[$stat->value] ?? 1.0;
        $temporal = $this->temporales[$stat->value] ?? 1.0;

        return $this->clampear($base * $temporal);
    }

    public function esNeutro(): bool
    {
        foreach (StatClave::cases() as $stat) {
            if ($this->obtenerMultiplicador($stat) !== 1.0) {
                return false;
            }
        }

        return true;
    }

    public function obtenerModificadores(): ModificadoresStatsCollection
    {
        $items = [];
        foreach (StatClave::cases() as $stat) {
            $items[] = new ModificadorStat($stat, $this->obtenerMultiplicador($stat));
        }

        return new ModificadoresStatsCollection($items);
    }

    public function obtenerEtapas(): MapaConteo
    {
        $etapas = [];
        foreach (StatClave::cases() as $stat) {
            $etapas[$stat->value] = $this->factorAEtapa($this->obtenerMultiplicador($stat));
        }

        return MapaConteo::desdeArray($etapas);
    }

    /**
     * @return array{base: array<string, float>, temporales: array<string, float>, turnos: int}
     */
    public function toArray(): array
    {
        return [
            'base' => $this->factores,
            'temporales' => $this->temporales,
            'turnos' => $this->turnosTemporales,
        ];
    }

    public function turnosTemporales(): int
    {
        return $this->turnosTemporales;
    }

    public function tieneTemporales(): bool
    {
        return $this->temporales !== [];
    }

    private function factorAEtapa(float $factor): int
    {
        if ($factor >= 1.0) {
            return (int) round(2 * $factor - 2);
        }

        return (int) round(2 - 2 / $factor);
    }

    private function clampear(float $factor): float
    {
        return max($this->limiteInferior, min($this->limiteSuperior, $factor));
    }
}
