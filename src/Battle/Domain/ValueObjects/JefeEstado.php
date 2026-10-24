<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\Collections\EventosFaseJefeCollection;
use Src\Battle\Domain\Collections\PartesJefeCollection;

/**
 * Estado de un jefe: fases por umbral de vida, enrage y puntos débiles.
 *
 * El jefe comparte el flujo normal de combate (3v1); este objeto solo añade
 * las transiciones y multiplicadores propios del módulo de jefes.
 */
class JefeEstado
{
    /** @var list<array{umbral: float, nombre: string, bonificador_dano: float, bonificador_velocidad: float, enrage: bool}> */
    private array $fases;

    /** @var list<ParteJefe> */
    private array $partes = [];

    private int $faseActual = 0;

    private bool $enrage = false;

    /**
     * @param  list<array<string, mixed>>  $fases
     * @param  list<array<string, mixed>>  $partes
     */
    public function __construct(
        array $fases,
        array $partes,
        float $hpMax,
    ) {
        $this->fases = $this->normalizarFases($fases);
        $this->partes = $this->normalizarPartes($partes, $hpMax);
    }

    /**
     * @param  list<array<string, mixed>>  $fases
     * @return list<array{umbral: float, nombre: string, bonificador_dano: float, bonificador_velocidad: float, enrage: bool}>
     */
    private function normalizarFases(array $fases): array
    {
        $normalizadas = [];
        foreach ($fases as $fase) {
            $umbral = (float) ($fase['umbral'] ?? 0.0);
            if ($umbral <= 0.0 || $umbral >= 1.0) {
                continue;
            }

            $normalizadas[] = [
                'umbral' => $umbral,
                'nombre' => (string) ($fase['nombre'] ?? 'Fase'),
                'bonificador_dano' => max(1.0, (float) ($fase['bonificador_dano'] ?? 1.0)),
                'bonificador_velocidad' => max(1.0, (float) ($fase['bonificador_velocidad'] ?? 1.0)),
                'enrage' => (bool) ($fase['enrage'] ?? false),
            ];
        }

        usort(
            $normalizadas,
            static fn (array $a, array $b): int => $b['umbral'] <=> $a['umbral'],
        );

        if ($normalizadas === []) {
            $normalizadas = [
                ['umbral' => 0.75, 'nombre' => 'Fase 2', 'bonificador_dano' => 1.15, 'bonificador_velocidad' => 1.10, 'enrage' => false],
                ['umbral' => 0.50, 'nombre' => 'Fase 3', 'bonificador_dano' => 1.30, 'bonificador_velocidad' => 1.20, 'enrage' => false],
                ['umbral' => 0.25, 'nombre' => 'Enrage', 'bonificador_dano' => 1.50, 'bonificador_velocidad' => 1.30, 'enrage' => true],
            ];
        }

        return $normalizadas;
    }

    /**
     * @param  list<array<string, mixed>>  $partes
     * @return list<ParteJefe>
     */
    private function normalizarPartes(array $partes, float $hpMax): array
    {
        $resultado = [];
        $indice = 1;

        foreach ($partes as $parte) {
            $fraccion = max(0.01, min(1.0, (float) ($parte['fraccion_hp'] ?? 0.2)));
            $id = (string) ($parte['id'] ?? 'parte_'.$indice);
            $resultado[] = new ParteJefe(
                id: $id,
                nombre: (string) ($parte['nombre'] ?? 'Parte '.$indice),
                hp: max(1.0, $hpMax * $fraccion),
                hpMax: max(1.0, $hpMax * $fraccion),
            );
            $indice++;
        }

        if ($resultado === []) {
            $resultado[] = new ParteJefe('nucleo', 'Núcleo', max(1.0, $hpMax * 0.2), max(1.0, $hpMax * 0.2));
        }

        return $resultado;
    }

    /**
     * Activa las fases cuyo umbral ya se cruzó.
     */
    public function procesarDanio(float $hpActual, float $hpMax): EventosFaseJefeCollection
    {
        $eventos = new EventosFaseJefeCollection();

        if ($hpMax <= 0.0) {
            return $eventos;
        }

        $fraccion = max(0.0, min(1.0, $hpActual / $hpMax));

        while (isset($this->fases[$this->faseActual])
            && $fraccion <= $this->fases[$this->faseActual]['umbral']) {
            $fase = $this->fases[$this->faseActual];
            $this->faseActual++;
            $this->enrage = $this->enrage || $fase['enrage'];

            $eventos->add(new EventoFaseJefe(
                tipo: 'fase',
                nombre: $fase['nombre'],
                bonificadorDano: $fase['bonificador_dano'],
                bonificadorVelocidad: $fase['bonificador_velocidad'],
                enrage: $fase['enrage'],
            ));
        }

        return $eventos;
    }

    /**
     * Aplica el daño a la primera parte no rota. Devuelve true si esta
     * impactó y rompió una parte.
     */
    public function daniarParte(float $dano): bool
    {
        foreach ($this->partes as $parte) {
            if (! $parte->estaRota()) {
                return $parte->recibirDanio($dano);
            }
        }

        return false;
    }

    public function faseActual(): int
    {
        return $this->faseActual;
    }

    public function nombreFaseActual(): string
    {
        if ($this->faseActual === 0) {
            return 'Fase 1';
        }

        return $this->fases[$this->faseActual - 1]['nombre'] ?? 'Fase '.$this->faseActual;
    }

    public function enrage(): bool
    {
        return $this->enrage;
    }

    public function multiplicadorDanioInfligido(): float
    {
        if ($this->faseActual === 0) {
            return 1.0;
        }

        return $this->fases[$this->faseActual - 1]['bonificador_dano'] ?? 1.0;
    }

    public function multiplicadorVelocidad(): float
    {
        if ($this->faseActual === 0) {
            return 1.0;
        }

        return $this->fases[$this->faseActual - 1]['bonificador_velocidad'] ?? 1.0;
    }

    public function partes(): PartesJefeCollection
    {
        return new PartesJefeCollection($this->partes);
    }

    public function tienePartesRotas(): bool
    {
        foreach ($this->partes as $parte) {
            if ($parte->estaRota()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{faseActual: int, nombreFase: string, enrage: bool, multiplicadorDano: float, multiplicadorVelocidad: float, partes: list<array{id: string, nombre: string, hp: float, hpMax: float, roto: bool}>}
     */
    public function toArray(): array
    {
        return [
            'faseActual' => $this->faseActual,
            'nombreFase' => $this->nombreFaseActual(),
            'enrage' => $this->enrage,
            'multiplicadorDano' => $this->multiplicadorDanioInfligido(),
            'multiplicadorVelocidad' => $this->multiplicadorVelocidad(),
            'partes' => array_map(
                static fn (ParteJefe $parte): array => $parte->toArray(),
                $this->partes,
            ),
        ];
    }
}
