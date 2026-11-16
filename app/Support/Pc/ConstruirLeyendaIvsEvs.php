<?php

declare(strict_types=1);

namespace App\Support\Pc;

use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Pokemon\Domain\Stats\BattleStats;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Domain\PerfilIvsEvs;

/**
 * Construye la leyenda de perfiles IV/EV y stats anónimos del rival.
 *
 * Los cálculos se delegan en `BattleStats` (fuente única de fórmulas) y en los
 * métodos de `PerfilIvsEvs` (fuente única de IV/EV). No se codifican valores
 * como 15/255/8/128 ni fórmulas en este builder.
 */
final class ConstruirLeyendaIvsEvs
{
    private const MAXIMO_POKEMON = 3;

    public function construir(
        ?PerfilIvsEvs $perfilActual,
        int $nivelStats,
        DatosPokemonBatallaCollection $equipoRival,
    ): LeyendaIvsEvs {
        return new LeyendaIvsEvs(
            actual: $perfilActual === null
                ? null
                : new PerfilRivalAplicado(
                    clave: $perfilActual->value,
                    etiqueta: $this->etiqueta($perfilActual),
                    nivelStats: $nivelStats,
                ),
            perfiles: $this->construirPerfiles($perfilActual),
            pokemons: $this->construirPokemons($equipoRival, $nivelStats),
        );
    }

    /**
     * @return list<PerfilIvsEvsOpcion>
     */
    private function construirPerfiles(?PerfilIvsEvs $perfilActual): array
    {
        $opciones = [];

        foreach (PerfilIvsEvs::cases() as $perfil) {
            $opciones[] = new PerfilIvsEvsOpcion(
                clave: $perfil->value,
                etiqueta: $this->etiqueta($perfil),
                descripcion: $this->descripcion($perfil),
                statsAlMaximo: $perfil->cuantosMaximos(),
                ivMaximo: $perfil->ivMaximo(),
                ivResto: $perfil->ivResto(),
                evMaximo: $perfil->evMaximo(),
                evResto: $perfil->evResto(),
                actual: $perfil === $perfilActual,
            );
        }

        return $opciones;
    }

    /**
     * Construye la lista anónima de Pokémon rival.
     *
     * El orden es neutral: se basa en un hash determinista de la identidad de
     * cada Pokémon y no en su posición en la colección, para que las etiquetas
     * "Rival 1..3" no revelen la formación vanguardia/retaguardia del rival.
     *
     * @return list<PokemonRivalPreview>
     */
    private function construirPokemons(
        DatosPokemonBatallaCollection $equipoRival,
        int $nivelStats,
    ): array {
        $pokemons = [];

        $datosOrdenados = $equipoRival->all();
        usort($datosOrdenados, static function (DatosPokemonBatalla $a, DatosPokemonBatalla $b): int {
            return strcmp(
                hash('sha256', $a->nombre.'|'.$a->speciesId.'|'.$a->id),
                hash('sha256', $b->nombre.'|'.$b->speciesId.'|'.$b->id),
            );
        });

        $datosRival = array_slice($datosOrdenados, 0, self::MAXIMO_POKEMON);

        foreach ($datosRival as $indice => $datos) {
            $pokemons[] = new PokemonRivalPreview(
                etiqueta: 'Rival '.($indice + 1),
                stats: $this->construirStats($datos, $nivelStats),
            );
        }

        return $pokemons;
    }

    private function construirStats(DatosPokemonBatalla $datos, int $nivelStats): StatsPreview
    {
        $stats = new BattleStats(
            stats: new StatsValue(
                hp: (float) $datos->hp,
                attack: (float) $datos->atk,
                defense: (float) $datos->def,
                spAtk: (float) $datos->spAtk,
                spDef: (float) $datos->spDef,
                speed: (float) $datos->speed,
            ),
            evs: $datos->evs,
            nivel: $datos->nivel ?? $nivelStats,
            ivs: $datos->ivs,
        );

        return new StatsPreview([
            $this->stat('hp', 'PS', $datos->hp, $datos->ivs->hp, $datos->evs->hp, $stats->hp),
            $this->stat('atk', 'Ataque', $datos->atk, $datos->ivs->attack, $datos->evs->attack, $stats->attack),
            $this->stat('def', 'Defensa', $datos->def, $datos->ivs->defense, $datos->evs->defense, $stats->defense),
            $this->stat('spAtk', 'At. Esp.', $datos->spAtk, $datos->ivs->spAtk, $datos->evs->spAtk, $stats->spAtk),
            $this->stat('spDef', 'Def. Esp.', $datos->spDef, $datos->ivs->spDef, $datos->evs->spDef, $stats->spDef),
            $this->stat('speed', 'Velocidad', $datos->speed, $datos->ivs->speed, $datos->evs->speed, $stats->speed),
        ]);
    }

    private function stat(
        string $clave,
        string $etiqueta,
        int $base,
        ?float $iv,
        ?float $ev,
        float $calculado,
    ): StatPreview {
        return new StatPreview(
            clave: $clave,
            etiqueta: $etiqueta,
            base: $base,
            iv: (int) ($iv ?? 0.0),
            ev: (int) ($ev ?? 0.0),
            calculado: (int) $calculado,
        );
    }

    private function etiqueta(PerfilIvsEvs $perfil): string
    {
        return match ($perfil) {
            PerfilIvsEvs::Nulo => 'Sin IV/EV',
            PerfilIvsEvs::TopUno => 'Top 1',
            PerfilIvsEvs::TopDos => 'Top 2',
            PerfilIvsEvs::Completo => 'Todo al máximo',
        };
    }

    private function descripcion(PerfilIvsEvs $perfil): string
    {
        $maximos = $perfil->cuantosMaximos();
        $ivMaximo = $perfil->ivMaximo();
        $evMaximo = $perfil->evMaximo();
        $ivResto = $perfil->ivResto();
        $evResto = $perfil->evResto();

        if ($maximos === PerfilIvsEvs::Nulo->cuantosMaximos()) {
            return "Ningún stat recibe el máximo; todos llevan IV {$ivResto} y EV {$evResto}.";
        }

        if ($maximos === PerfilIvsEvs::Completo->cuantosMaximos()) {
            return "Todos los stats reciben el máximo: IV {$ivMaximo} y EV {$evMaximo}.";
        }

        $sujeto = $maximos === 1
            ? 'El stat con mayor base recibe'
            : "Los {$maximos} stats con mayor base reciben";

        return "{$sujeto} el máximo (IV {$ivMaximo}, EV {$evMaximo}); el resto lleva IV {$ivResto} y EV {$evResto}.";
    }
}
