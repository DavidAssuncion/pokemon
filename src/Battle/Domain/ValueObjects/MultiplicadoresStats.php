<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\Enums\StatClave;

/**
 * Value Object inmutable que encapsula modificadores multiplicativos
 * de precisión/estadísticas (float) para un combatiente.
 *
 * Cada estadística tiene un factor float (default 1.0 = sin cambio).
 * Los factores se clampean entre MIN y MAX por defecto (0.25–4.0),
 * equivalente a las etapas −6..+6 del sistema clásico.
 *
 * Permiten reducciones parciales (ej: ×0.9375 por turno) y
 * en el futuro bonus de daño (+15% eléctrico, medallas).
 */
final class MultiplicadoresStats
{
    public const float DEFAULT_MIN = 0.25;

    public const float DEFAULT_MAX = 4.0;

    /** @var array<string, float> */
    private array $factores;

    private float $limiteInferior;

    private float $limiteSuperior;

    /**
     * Constructor interno. Usa `desdeEtapas` o `desdeSerializado` para crear instancias.
     *
     * @param  array<string, float>  $factores  Claves StatClave->value, valores float.
     */
    private function __construct(
        array $factores,
        float $limiteInferior = self::DEFAULT_MIN,
        float $limiteSuperior = self::DEFAULT_MAX,
    ) {
        $this->limiteInferior = $limiteInferior;
        $this->limiteSuperior = $limiteSuperior;
        $this->factores = [];

        foreach ($factores as $clave => $factor) {
            StatClave::from($clave);
            $this->factores[$clave] = $this->clampear($factor);
        }
    }

    // ─── Fábricas ──────────────────────────────────────────────

    /**
     * Convierte una etapa entera (-6..+6) a su factor multiplicativo exacto:
     *  n >= 0: (2+n)/2   (ej: +1 → ×1.5, +6 → ×4.0)
     *  n < 0:  2/(2-n)   (ej: -1 → ×0.667, -6 → ×0.25)
     */
    public static function factorDesdeStages(int $n): float
    {
        return $n >= 0
            ? (2 + $n) / 2
            : 2 / (2 - $n);
    }

    /**
     * Crea una instancia vacía (todos los factores en 1.0) con los stat claves por defecto.
     */
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
     * Crea una instancia desde un array serializado de factores float.
     * Acepta claves válidas de StatClave; ignora claves desconocidas.
     *
     * @param  array<string, float>  $factores
     */
    public static function desdeSerializado(array $factores): self
    {
        $normalizados = [];
        foreach ($factores as $clave => $factor) {
            if (StatClave::tryFrom($clave) !== null) {
                $normalizados[$clave] = $factor;
            }
        }

        return new self($normalizados);
    }

    /**
     * Migra desde el formato antiguo de etapas enteras (−6..+6)
     * usando la tabla exacta de conversión:
     *  n >= 0: (2+n)/2   (ej: +1 → 1.5, +6 → 4.0)
     *  n < 0:  2/(2−n)   (ej: -1 → 0.667, -6 → 0.25).
     *
     * @param  array<string, int>  $etapasInt  Claves StatClave->value, valores int −6..+6.
     */
    public static function desdeEtapas(array $etapasInt): self
    {
        $factores = [];
        foreach ($etapasInt as $clave => $etapa) {
            if (StatClave::tryFrom($clave) === null) {
                continue;
            }
            $factores[$clave] = self::factorDesdeStages((int) $etapa);
        }

        return new self($factores);
    }

    // ─── Operaciones inmutables ────────────────────────────────

    /**
     * Multiplica el factor actual de una estadística por el factor dado.
     */
    public function aplicarFactor(StatClave $stat, float $factor): self
    {
        $nuevos = $this->factores;
        $clave = $stat->value;
        $actual = $nuevos[$clave] ?? 1.0;
        $nuevos[$clave] = $this->clampear($actual * $factor);

        return new self($nuevos, $this->limiteInferior, $this->limiteSuperior);
    }

    /**
     * Aplica un porcentaje de cambio a una estadística.
     * Ejemplo: +15 → ×1.15; −6.25 → ×0.9375.
     */
    public function aplicarPorcentaje(StatClave $stat, float $porcentaje): self
    {
        return $this->aplicarFactor($stat, 1 + $porcentaje / 100);
    }

    /**
     * Combina con otro conjunto de multiplicadores: producto por clave.
     */
    public function combinar(self $otros): self
    {
        $nuevos = $this->factores;
        foreach ($otros->factores as $clave => $factor) {
            $actual = $nuevos[$clave] ?? 1.0;
            $nuevos[$clave] = $this->clampear($actual * $factor);
        }

        return new self($nuevos, $this->limiteInferior, $this->limiteSuperior);
    }

    /**
     * Crea una nueva instancia con límites personalizados por StatClave.
     * Útil si en el futuro se necesitan ranges distintos por stat.
     */
    public function conLimites(float $min, float $max): self
    {
        $nuevos = [];
        foreach ($this->factores as $clave => $factor) {
            $nuevos[$clave] = max($min, min($max, $factor));
        }

        return new self($nuevos, $min, $max);
    }

    // ─── Consultas ─────────────────────────────────────────────

    /**
     * Obtiene el factor multiplicador para una estadística (default 1.0).
     */
    public function obtenerMultiplicador(StatClave $stat): float
    {
        return $this->factores[$stat->value] ?? 1.0;
    }

    /**
     * Indica si todos los factores son 1.0 (sin modificaciones).
     */
    public function esNeutro(): bool
    {
        foreach ($this->factores as $factor) {
            if ($factor !== 1.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Retorna la colección tipada de modificadores.
     */
    public function obtenerModificadores(): ModificadoresStatsCollection
    {
        $items = [];
        foreach ($this->factores as $clave => $factor) {
            $items[] = new ModificadorStat(StatClave::from($clave), $factor);
        }

        return new ModificadoresStatsCollection($items);
    }

    /**
     * Convierte factores actuales a enteros de etapa equivalentes (−6..+6)
     * para mantener compatibilidad visual con la UI (blades).
     *
     * @return array<string, int>
     */
    public function obtenerEtapas(): array
    {
        $etapas = [];
        foreach ($this->factores as $clave => $factor) {
            $etapas[$clave] = $this->factorAEtapa($factor);
        }

        return $etapas;
    }

    /**
     * Convierte un factor a la etapa entera más cercana.
     */
    private function factorAEtapa(float $factor): int
    {
        if ($factor >= 1.0) {
            // n >= 0: factor = (2+n)/2 → n = 2*factor - 2
            return (int) round(2 * $factor - 2);
        }

        // n < 0: factor = 2/(2-n) → n = 2 - 2/factor
        return (int) round(2 - 2 / $factor);
    }

    /**
     * Clampea un factor entre los límites configurados.
     */
    private function clampear(float $factor): float
    {
        return max($this->limiteInferior, min($this->limiteSuperior, $factor));
    }
}
