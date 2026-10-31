<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Catalog;

use Src\Battle\Domain\Collections\MovimientoBatallaCollection;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Elimina movimientos redundantes de un loadout de combate.
 *
 * Dos movimientos se consideran equivalentes si comparten tipo, categoría,
 * precisión, objetivo, prioridad, flags y todo su perfil de efectos (estado,
 * cambios de stats, drenaje, recoil, efectos secundarios, etc.). En ese caso
 * solo se conserva el de mayor potencia. Si cambia cualquier efecto o la
 * precisión, ambos se mantienen como opciones distintas.
 *
 * El objetivo es que la IA y el jugador no vean, por ejemplo, Ascuas y
 * Lanzallamas a la vez cuando Lanzallamas es estrictamente mejor.
 */
final class DepuradorMovimientos
{
    /**
     * Flags que cambian alguna mecánica del combate. Se excluyen las
     * puramente cosméticas o de restricciones de copia (mirror, metronome,
     * failcopycat, etc.) para no impedir la deduplicación.
     *
     * @var list<string>
     */
    private const FLAGS_RELEVANTES = [
        'contact',
        'protect',
        'punch',
        'sound',
        'bullet',
        'pulse',
        'bite',
        'slicing',
        'wind',
        'dance',
        'powder',
        'heal',
        'charge',
        'recharge',
        'futuremove',
        'defrost',
        'minimize',
        'gravity',
        'nonsky',
        'bypasssub',
        'mustpressure',
    ];

    public function depurar(MovimientoBatallaCollection $movimientos): MovimientoBatallaCollection
    {
        /** @var array<string, MovimientoBatalla> $grupos */
        $grupos = [];

        foreach ($movimientos as $movimiento) {
            $firma = $this->firma($movimiento);

            if (! isset($grupos[$firma])) {
                $grupos[$firma] = $movimiento;

                continue;
            }

            if ($this->esMejor($movimiento, $grupos[$firma])) {
                $grupos[$firma] = $movimiento;
            }
        }

        return new MovimientoBatallaCollection(array_values($grupos));
    }

    private function firma(MovimientoBatalla $movimiento): string
    {
        $datos = $movimiento->__serialize();

        unset(
            $datos['nombre'],
            $datos['potencia'],
            $datos['nivelRequerido'],
            $datos['statusEffect'],
            $datos['flags'],
            $datos['secundarios'],
            $datos['autoEfectos'],
        );
        unset($datos['perfil']['probabilidadEstado']);

        $datos['efectos'] = $this->efectosCanonicos($movimiento);
        $datos['flags_relevantes'] = $this->flagsRelevantes($movimiento);

        return json_encode(
            $this->normalizar($datos),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Unifica `statusEffect` con los efectos secundarios para que un estado
     * garantizado y uno secundario con la misma probabilidad se comparen igual.
     *
     * @return list<array<string, mixed>>
     */
    private function efectosCanonicos(MovimientoBatalla $movimiento): array
    {
        $efectos = [];

        if ($movimiento->statusEffect !== EstadoPokemon::NONE) {
            $efectos[] = [
                'probabilidad' => (int) round($movimiento->perfil->probabilidadEstado * 100),
                'estado' => $movimiento->statusEffect->value,
                'cambios' => [],
                'esAuto' => false,
                'condicionVolatil' => null,
            ];
        }

        foreach ($movimiento->secundarios as $efecto) {
            $efectos[] = $efecto->toArray();
        }

        foreach ($movimiento->autoEfectos as $efecto) {
            $efectos[] = $efecto->toArray();
        }

        return $efectos;
    }

    /**
     * @return list<string>
     */
    private function flagsRelevantes(MovimientoBatalla $movimiento): array
    {
        $flags = [];

        foreach ($movimiento->flags as $flag) {
            if (in_array($flag->value, self::FLAGS_RELEVANTES, true)) {
                $flags[] = $flag->value;
            }
        }

        sort($flags);

        return $flags;
    }

    private function esMejor(MovimientoBatalla $candidato, MovimientoBatalla $actual): bool
    {
        if ($candidato->potencia !== $actual->potencia) {
            return $candidato->potencia > $actual->potencia;
        }

        if ($candidato->nivelRequerido !== $actual->nivelRequerido) {
            return $candidato->nivelRequerido < $actual->nivelRequerido;
        }

        return $candidato->nombre < $actual->nombre;
    }

    /**
     * Normaliza arrays para que el orden de claves/efectos no cambie la firma.
     */
    private function normalizar(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor;
        }

        if (array_is_list($valor)) {
            $items = array_map(fn (mixed $item): mixed => $this->normalizar($item), $valor);
            usort(
                $items,
                static fn (mixed $a, mixed $b): int => json_encode($a) <=> json_encode($b),
            );

            return $items;
        }

        ksort($valor);
        foreach ($valor as $clave => $item) {
            $valor[$clave] = $this->normalizar($item);
        }

        return $valor;
    }
}
