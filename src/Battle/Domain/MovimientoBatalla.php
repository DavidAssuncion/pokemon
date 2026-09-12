<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Collections\CambiosStatsCollection;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Shared\Tipos\TipoPokemon;

class MovimientoBatalla
{
    public function __construct(
        public readonly string $nombre,
        public readonly int $potencia,
        public readonly TipoPokemon $tipo,
        public readonly CategoriaMovimiento $categoria,
        public readonly EstadoPokemon $statusEffect = EstadoPokemon::NONE,
        public readonly int $priority = 0,
        public readonly CambiosStatsCollection $selfStatChanges = new CambiosStatsCollection(),
        public readonly CambiosStatsCollection $targetStatChanges = new CambiosStatsCollection(),
    ) {
    }

    /**
     * Serializa a sesión con las colecciones en forma de array {stat, factor}.
     * El formato legacy v9 (array {stat, stages}) se migra en __unserialize.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'nombre' => $this->nombre,
            'potencia' => $this->potencia,
            'tipo' => $this->tipo->value,
            'categoria' => $this->categoria->value,
            'statusEffect' => $this->statusEffect->value,
            'priority' => $this->priority,
            'selfStatChanges' => $this->cambiosParaSerializar($this->selfStatChanges),
            'targetStatChanges' => $this->cambiosParaSerializar($this->targetStatChanges),
        ];
    }

    /**
     * Restaura desde sesión aceptando el formato actual ({stat, factor}) y el
     * legacy v9 ({stat, stages}); las claves de stat desconocidas se ignoran.
     *
     * @param  array<string, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        $this->nombre = (string) ($data['nombre'] ?? '');
        $this->potencia = (int) ($data['potencia'] ?? 0);
        $this->tipo = TipoPokemon::from((int) ($data['tipo'] ?? TipoPokemon::NORMAL->value));
        $this->categoria = CategoriaMovimiento::from((string) ($data['categoria'] ?? CategoriaMovimiento::FISICO->value));
        $this->statusEffect = EstadoPokemon::tryFrom((string) ($data['statusEffect'] ?? EstadoPokemon::NONE->value)) ?? EstadoPokemon::NONE;
        $this->priority = (int) ($data['priority'] ?? 0);
        $this->selfStatChanges = self::cambiosDesdeSerializado($data['selfStatChanges'] ?? []);
        $this->targetStatChanges = self::cambiosDesdeSerializado($data['targetStatChanges'] ?? []);
    }

    public function esEspecial(): bool
    {
        return $this->categoria === CategoriaMovimiento::ESPECIAL;
    }

    public function esFisico(): bool
    {
        return $this->categoria === CategoriaMovimiento::FISICO;
    }

    public function esEstado(): bool
    {
        return $this->categoria === CategoriaMovimiento::ESTADO;
    }

    public function tieneStatus(): bool
    {
        return $this->statusEffect !== EstadoPokemon::NONE;
    }

    public function tieneSelfStatChanges(): bool
    {
        return ! $this->selfStatChanges->isEmpty();
    }

    public function tieneTargetStatChanges(): bool
    {
        return ! $this->targetStatChanges->isEmpty();
    }

    /**
     * @return array<int, array{stat: string, factor: float}>
     */
    private function cambiosParaSerializar(CambiosStatsCollection $cambios): array
    {
        $resultado = [];
        foreach ($cambios as $cambio) {
            $resultado[] = ['stat' => $cambio->statValue(), 'factor' => $cambio->factor];
        }

        return $resultado;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cambios
     */
    private static function cambiosDesdeSerializado(array $cambios): CambiosStatsCollection
    {
        $items = [];
        foreach ($cambios as $cambio) {
            $stat = StatClave::tryFrom((string) ($cambio['stat'] ?? ''));
            if ($stat === null) {
                continue;
            }

            if (array_key_exists('factor', $cambio)) {
                $items[] = new CambioStat($stat, (float) $cambio['factor']);
            } elseif (array_key_exists('stages', $cambio)) {
                $items[] = CambioStat::desdeEtapas($stat, (int) $cambio['stages']);
            }
        }

        return new CambiosStatsCollection($items);
    }
}
