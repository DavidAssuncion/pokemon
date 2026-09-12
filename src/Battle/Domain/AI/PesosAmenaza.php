<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\Enums\ClaveEfecto;
use Src\Battle\Domain\Enums\ClaveItem;

/**
 * Pesos configurables para el cálculo de amenaza y evaluación de acciones.
 * Centraliza todos los valores mágicos para que sean escalables sin modificar la lógica.
 */
final class PesosAmenaza
{
    /** @var array<int|string, float> */
    private array $items;

    /** @var array<int|string, float> */
    private array $efectos;

    /**
     * @param  array<int|string, float>  $pesosItems
     * @param  array<int|string, float>  $pesosEfectos
     */
    public function __construct(
        // ─── Pesos de amenaza por componente ───
        public readonly float $pesoOfensiva = 1.0,
        public readonly float $pesoKO = 1.0,
        public readonly float $pesoVelocidad = 1.0,
        public readonly float $pesoSetup = 1.0,
        public readonly float $pesoEstrategica = 1.0,
        // ─── Amenaza por setup ───
        public readonly float $puntosPorEtapaPositiva = 20.0,
        // ─── Amenaza por velocidad ───
        public readonly float $puntosVelocidadSuperior = 50.0,
        // ─── Amenaza de KO ───
        public readonly float $puntosKOPosible = 100.0,
        // ─── Evaluación de acciones ───
        public readonly float $puntosKO = 100.0,
        public readonly float $multiplicadorDanio = 40.0,
        public readonly float $puntosSupervivencia = 50.0,
        public readonly float $puntosRiesgo = 30.0,
        // ─── Posición global ───
        public readonly float $puntosVentajaNumerica = 100.0,
        public readonly float $puntosAliadoSano = 50.0,
        public readonly float $puntosAliadoHerido = -50.0,
        // ─── Pesos por componente (items / efectos) ───
        array $pesosItems = [ClaveItem::ORBE_VIDA->value => 30.0],
        array $pesosEfectos = [ClaveEfecto::PERFORACION_ARMADURA->value => 20.0],
    ) {
        $this->items = $pesosItems;
        $this->efectos = $pesosEfectos;
    }

    // ─── Consulta de pesos por clave ─────────────────────────

    /**
     * Puntos de amenaza de un objeto equipado.
     *
     * @param  string|ClaveItem  $clave
     */
    public function amenazaItem(string|ClaveItem $clave): float
    {
        $key = $clave instanceof ClaveItem ? $clave->value : $clave;

        return $this->items[$key] ?? 0.0;
    }

    /**
     * Puntos de amenaza de un efecto/habilidad.
     *
     * @param  string|ClaveEfecto  $clave
     */
    public function amenazaEfecto(string|ClaveEfecto $clave): float
    {
        $key = $clave instanceof ClaveEfecto ? $clave->value : $clave;

        return $this->efectos[$key] ?? 0.0;
    }

    /**
     * Retorna todos los efectos registrados y sus pesos.
     *
     * @return array<int|string, float>
     */
    public function efectosRegistrados(): array
    {
        return $this->efectos;
    }

    /**
     * Registra o sobrescribe un item con su peso de amenaza.
     *
     * @param  string|ClaveItem  $clave
     */
    public function registrarItem(string|ClaveItem $clave, float $puntos): self
    {
        $key = $clave instanceof ClaveItem ? $clave->value : $clave;
        $nuevo = clone $this;
        $nuevo->items[$key] = $puntos;

        return $nuevo;
    }

    /**
     * Registra o sobrescribe un efecto/habilidad con su peso de amenaza.
     *
     * @param  string|ClaveEfecto  $clave
     */
    public function registrarEfecto(string|ClaveEfecto $clave, float $puntos): self
    {
        $key = $clave instanceof ClaveEfecto ? $clave->value : $clave;
        $nuevo = clone $this;
        $nuevo->efectos[$key] = $puntos;

        return $nuevo;
    }

    // ─── Factory para dificultades ──────────────────────────

    /**
     * @param  array<int|string, float>|null  $pesosItems
     * @param  array<int|string, float>|null  $pesosEfectos
     */
    public static function porDefecto(
        ?array $pesosItems = null,
        ?array $pesosEfectos = null,
    ): self {
        return new self(
            pesosItems: $pesosItems ?? [ClaveItem::ORBE_VIDA->value => 30.0],
            pesosEfectos: $pesosEfectos ?? [ClaveEfecto::PERFORACION_ARMADURA->value => 20.0],
        );
    }

    /**
     * @param  array<int|string, float>|null  $pesosItems
     * @param  array<int|string, float>|null  $pesosEfectos
     */
    public static function normal(
        ?array $pesosItems = null,
        ?array $pesosEfectos = null,
    ): self {
        return new self(
            pesoOfensiva: 0.7,
            pesoKO: 0.7,
            pesoVelocidad: 0.7,
            pesoSetup: 0.5,
            pesoEstrategica: 0.5,
            pesosItems: $pesosItems ?? [ClaveItem::ORBE_VIDA->value => 30.0],
            pesosEfectos: $pesosEfectos ?? [ClaveEfecto::PERFORACION_ARMADURA->value => 20.0],
        );
    }
}
