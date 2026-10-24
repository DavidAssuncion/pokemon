<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\Collections\CambiosStatsCollection;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;

/**
 * Efecto adicional de un movimiento con su propia probabilidad.
 *
 * Puede afectar al objetivo (estado/cambios) o al usuario (`esAuto`).
 * Sustituye al modelo de un único `statusEffect` + `probabilidadEstado`.
 */
final readonly class EfectoSecundario
{
    public function __construct(
        public int $probabilidad = 100,
        public EstadoPokemon $estado = EstadoPokemon::NONE,
        public CambiosStatsCollection $cambios = new CambiosStatsCollection(),
        public bool $esAuto = false,
        public ?string $condicionVolatil = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArray(array $datos, bool $esAuto = false): self
    {
        $estado = self::estadoDesde($datos['status'] ?? null);
        $boosts = $datos['boosts'] ?? [];
        $cambios = isset($datos['cambios']) && is_array($datos['cambios'])
            ? self::cambiosDesdeFactores($datos['cambios'])
            : self::cambiosDesdeArray(is_array($boosts) ? $boosts : []);
        $volatil = $datos['volatileStatus'] ?? $datos['condicionVolatil'] ?? null;

        return new self(
            probabilidad: max(0, min(100, (int) ($datos['chance'] ?? $datos['probabilidad'] ?? 100))),
            estado: $estado,
            cambios: $cambios,
            esAuto: $esAuto,
            condicionVolatil: is_string($volatil) ? $volatil : null,
        );
    }

    public function tieneEfecto(): bool
    {
        return $this->estado !== EstadoPokemon::NONE
            || ! $this->cambios->isEmpty()
            || $this->condicionVolatil !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $cambios = [];
        foreach ($this->cambios as $cambio) {
            $cambios[] = ['stat' => $cambio->statValue(), 'factor' => $cambio->factor];
        }

        return [
            'probabilidad' => $this->probabilidad,
            'estado' => $this->estado->value,
            'cambios' => $cambios,
            'esAuto' => $this->esAuto,
            'condicionVolatil' => $this->condicionVolatil,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cambios
     */
    private static function cambiosDesdeFactores(array $cambios): CambiosStatsCollection
    {
        $items = [];
        foreach ($cambios as $cambio) {
            $stat = StatClave::tryFrom((string) ($cambio['stat'] ?? ''));
            if ($stat !== null) {
                $items[] = new CambioStat($stat, (float) ($cambio['factor'] ?? 1.0));
            }
        }

        return new CambiosStatsCollection($items);
    }

    private static function estadoDesde(mixed $estado): EstadoPokemon
    {
        if (! is_string($estado) || $estado === '') {
            return EstadoPokemon::NONE;
        }

        $mapa = [
            'brn' => EstadoPokemon::BURN,
            'psn' => EstadoPokemon::POISON,
            'tox' => EstadoPokemon::BAD_POISON,
            'par' => EstadoPokemon::PARALYSIS,
            'slp' => EstadoPokemon::SLEEP,
            'frz' => EstadoPokemon::FREEZE,
            'confusion' => EstadoPokemon::CONFUSION,
        ];

        return $mapa[$estado] ?? EstadoPokemon::tryFrom($estado) ?? EstadoPokemon::NONE;
    }

    /**
     * @param  array<string, int|float>  $boosts
     */
    private static function cambiosDesdeArray(array $boosts): CambiosStatsCollection
    {
        $mapa = [
            'atk' => StatClave::ATAQUE,
            'def' => StatClave::DEFENSA,
            'spa' => StatClave::ATAQUE_ESPECIAL,
            'spd' => StatClave::DEFENSA_ESPECIAL,
            'spe' => StatClave::VELOCIDAD,
            'accuracy' => StatClave::PRECISION,
            'evasion' => StatClave::EVASION,
        ];

        $cambios = [];
        foreach ($boosts as $clave => $etapas) {
            $stat = $mapa[$clave] ?? StatClave::tryFrom($clave);
            if ($stat !== null) {
                $cambios[] = CambioStat::desdeEtapas($stat, (int) $etapas);
            }
        }

        return new CambiosStatsCollection($cambios);
    }
}
