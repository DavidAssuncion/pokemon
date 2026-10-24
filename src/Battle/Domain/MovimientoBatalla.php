<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Collections\CambiosStatsCollection;
use Src\Battle\Domain\Collections\EfectosSecundariosCollection;
use Src\Battle\Domain\Collections\FlagsMovimientoCollection;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\ObjetivoMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
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
        public readonly PerfilMovimiento $perfil = new PerfilMovimiento(),
        public readonly ObjetivoMovimiento $objetivo = ObjetivoMovimiento::NORMAL,
        public readonly FlagsMovimientoCollection $flags = new FlagsMovimientoCollection(),
        public readonly EfectosSecundariosCollection $secundarios = new EfectosSecundariosCollection(),
        public readonly EfectosSecundariosCollection $autoEfectos = new EfectosSecundariosCollection(),
        public readonly int $nivelRequerido = 1,
    ) {
    }

    /**
     * Copia del movimiento con otra potencia bruta. Conserva tipo, categoría,
     * estado, cambios y perfil; la usa el ejecutor de multi-golpe.
     */
    public function conPotencia(int $potencia): self
    {
        return new self(
            nombre: $this->nombre,
            potencia: $potencia,
            tipo: $this->tipo,
            categoria: $this->categoria,
            statusEffect: $this->statusEffect,
            priority: $this->priority,
            selfStatChanges: $this->selfStatChanges,
            targetStatChanges: $this->targetStatChanges,
            perfil: $this->perfil,
            objetivo: $this->objetivo,
            flags: $this->flags,
            secundarios: $this->secundarios,
            autoEfectos: $this->autoEfectos,
            nivelRequerido: $this->nivelRequerido,
        );
    }

    /**
     * Copia del movimiento con otro tipo efectivo (Meteorobola, Pulso de Campo,
     * Furia Taurina). Conserva potencia, categoría, efectos y perfil.
     */
    public function conTipo(TipoPokemon $tipo): self
    {
        if ($tipo === $this->tipo) {
            return $this;
        }

        return new self(
            nombre: $this->nombre,
            potencia: $this->potencia,
            tipo: $tipo,
            categoria: $this->categoria,
            statusEffect: $this->statusEffect,
            priority: $this->priority,
            selfStatChanges: $this->selfStatChanges,
            targetStatChanges: $this->targetStatChanges,
            perfil: $this->perfil,
            objetivo: $this->objetivo,
            flags: $this->flags,
            secundarios: $this->secundarios,
            autoEfectos: $this->autoEfectos,
            nivelRequerido: $this->nivelRequerido,
        );
    }

    public function esContacto(): bool
    {
        return $this->perfil->esContacto;
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
            'perfil' => $this->perfil->toArray(),
            'objetivo' => $this->objetivo->value,
            'flags' => $this->flags->toArray(),
            'secundarios' => array_map(
                static fn ($efecto): array => $efecto->toArray(),
                $this->secundarios->all(),
            ),
            'autoEfectos' => array_map(
                static fn ($efecto): array => $efecto->toArray(),
                $this->autoEfectos->all(),
            ),
            'nivelRequerido' => $this->nivelRequerido,
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
        $this->perfil = isset($data['perfil']) && is_array($data['perfil'])
            ? PerfilMovimiento::desdeArray($data['perfil'])
            : PerfilMovimiento::porDefecto();
        $this->objetivo = ObjetivoMovimiento::tryFrom((string) ($data['objetivo'] ?? ObjetivoMovimiento::NORMAL->value))
            ?? ObjetivoMovimiento::NORMAL;
        $this->flags = FlagsMovimientoCollection::desdeArray(
            isset($data['flags']) && is_array($data['flags']) ? $data['flags'] : [],
        );
        $this->secundarios = EfectosSecundariosCollection::desdeArray(
            isset($data['secundarios']) && is_array($data['secundarios']) ? $data['secundarios'] : [],
        );
        $this->autoEfectos = EfectosSecundariosCollection::desdeArray(
            isset($data['autoEfectos']) && is_array($data['autoEfectos']) ? $data['autoEfectos'] : [],
            true,
        );
        $this->nivelRequerido = max(1, (int) ($data['nivelRequerido'] ?? 1));
    }

    public function esEspecial(): bool
    {
        return ! $this->esEstado() && $this->categoria === CategoriaMovimiento::ESPECIAL;
    }

    public function esFisico(): bool
    {
        return ! $this->esEstado() && $this->categoria === CategoriaMovimiento::FISICO;
    }

    public function esEstado(): bool
    {
        // Un movimiento sin potencia no inflige daño: se trata como estado
        // aunque los datos de origen lo etiqueten como físico/especial
        // (ej. Tóxico o Fuego Fatuo).
        return $this->categoria === CategoriaMovimiento::ESTADO || $this->potencia <= 0;
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
            $resultado[] = ['stat' => $cambio->statValue(), 'factor' => $cambio->factor, 'etapas' => $cambio->etapas];
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
                $items[] = new CambioStat($stat, (float) $cambio['factor'], (int) ($cambio['etapas'] ?? 0));
            } elseif (array_key_exists('stages', $cambio)) {
                $items[] = CambioStat::desdeEtapas($stat, (int) $cambio['stages']);
            }
        }

        return new CambiosStatsCollection($items);
    }
}
