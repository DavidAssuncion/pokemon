<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

/**
 * Pesos configurables para el cálculo de amenaza y evaluación de acciones.
 * Centraliza todos los valores mágicos para que sean escalables sin modificar la lógica.
 */
final class PesosAmenaza
{
    /** @var array<string, float> */
    private array $items;

    /** @var array<string, float> */
    private array $efectos;

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
    ) {
        // Items: clave => puntos de amenaza
        $this->items = [
            'life_orb' => 30.0,
        ];

        // Efectos/habilidades: clave => puntos de amenaza
        $this->efectos = [
            'armor_pierce' => 20.0,
        ];
    }

    // ─── Consulta de pesos por clave ─────────────────────────

    /**
     * Puntos de amenaza de un objeto equipado.
     */
    public function amenazaItem(string $clave): float
    {
        return $this->items[$clave] ?? 0.0;
    }

    /**
     * Puntos de amenaza de un efecto/habilidad.
     */
    public function amenazaEfecto(string $clave): float
    {
        return $this->efectos[$clave] ?? 0.0;
    }

    /**
     * Retorna todos los efectos registrados y sus pesos.
     *
     * @return array<string, float>
     */
    public function efectosRegistrados(): array
    {
        return $this->efectos;
    }

    /**
     * Registra o sobrescribe un item con su peso de amenaza.
     */
    public function registrarItem(string $clave, float $puntos): self
    {
        $nuevo = clone $this;
        $nuevo->items[$clave] = $puntos;

        return $nuevo;
    }

    /**
     * Registra o sobrescribe un efecto/habilidad con su peso de amenaza.
     */
    public function registrarEfecto(string $clave, float $puntos): self
    {
        $nuevo = clone $this;
        $nuevo->efectos[$clave] = $puntos;

        return $nuevo;
    }

    // ─── Factory para dificultades ──────────────────────────

    public static function porDefecto(): self
    {
        return new self();
    }

    public static function normal(): self
    {
        return new self(
            pesoOfensiva: 0.7,
            pesoKO: 0.7,
            pesoVelocidad: 0.7,
            pesoSetup: 0.5,
            pesoEstrategica: 0.5,
        );
    }
}
