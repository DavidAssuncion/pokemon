<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain;

use Src\Shared\Tipos\TipoPokemon;

/**
 * Generación temporal de movimientos a partir de los tipos del pokémon.
 *
 * Mientras no existan datos reales de ataques/habilidades en BD, cada pokémon
 * recibe movimientos sintéticos según sus tipos:
 *
 * - Tipos no-Normal: 1 físico (60) + 1 especial (80) por tipo.
 * - Normal puro: 2 movimientos Normal de 80 y 100.
 * - Con tipos no-Normal: 2 movimientos Normal de 40 y 60.
 */
class GeneradorMovimientosTipo
{
    /**
     * @param  TipoPokemon[]  $tipos
     * @return list<array{nombre: string, potencia: int, tipo: TipoPokemon, categoria: string}>
     */
    public function generar(array $tipos): array
    {
        $tiposNoNormal = array_values(array_filter(
            $tipos,
            static fn (TipoPokemon $tipo): bool => $tipo !== TipoPokemon::NORMAL
        ));

        $movimientos = [];

        foreach ($tiposNoNormal as $tipo) {
            $movimientos[] = $this->movimiento("Golpe {$tipo->label()}", 60, $tipo, 'fisico');
            $movimientos[] = $this->movimiento("Ráfaga {$tipo->label()}", 80, $tipo, 'especial');
        }

        if ($tiposNoNormal === []) {
            $movimientos[] = $this->movimiento('Golpe Normal', 80, TipoPokemon::NORMAL, 'fisico');
            $movimientos[] = $this->movimiento('Ráfaga Normal', 100, TipoPokemon::NORMAL, 'especial');
        } else {
            $movimientos[] = $this->movimiento('Golpe Normal', 40, TipoPokemon::NORMAL, 'fisico');
            $movimientos[] = $this->movimiento('Ráfaga Normal', 60, TipoPokemon::NORMAL, 'especial');
        }

        return $movimientos;
    }

    /**
     * @return array{nombre: string, potencia: int, tipo: TipoPokemon, categoria: string}
     */
    private function movimiento(string $nombre, int $potencia, TipoPokemon $tipo, string $categoria): array
    {
        return [
            'nombre' => $nombre,
            'potencia' => $potencia,
            'tipo' => $tipo,
            'categoria' => $categoria,
        ];
    }
}
