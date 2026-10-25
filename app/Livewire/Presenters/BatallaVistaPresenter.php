<?php

declare(strict_types=1);

namespace App\Livewire\Presenters;

use Src\Battle\Domain\Enums\TipoClima;

/**
 * Presentador de la pantalla de combate.
 *
 * Recibe el estado público del componente Livewire y devuelve el contrato
 * completo que consumen `combate.blade.php` y sus partials. Toda la lógica de
 * presentación (clases, textos, orden de la cola, resumen de recompensas y
 * transformación del log) vive aquí; Blade sólo pinta.
 */
final class BatallaVistaPresenter
{
    /**
     * @param  list<array<string, mixed>>  $team1
     * @param  list<array<string, mixed>>  $team2
     * @param  list<array{team: int, index: int}>  $turnQueue
     * @param  list<string>  $log
     * @param  list<array<string, mixed>>  $eventos
     * @param  array<string, mixed>  $rewards
     * @return array{
     *     team1Vista: list<array<string, mixed>>,
     *     team2Vista: list<array<string, mixed>>,
     *     turnBar: array<string, mixed>,
     *     battleField: array<string, mixed>,
     *     logVista: list<array{html: string}>,
     *     rewardsVista: array<string, mixed>|null
     * }
     */
    public static function toView(
        array $team1,
        array $team2,
        array $turnQueue,
        string $phase,
        string $actingRefId,
        string $selectedTargetRefId,
        string $animAttackerId,
        string $animDefenderId,
        string $animAttackerNombre,
        string $animDefenderNombre,
        string $animMoveNombre,
        string $weather,
        array $log,
        array $eventos,
        array $rewards,
    ): array {
        $faseSeleccion = in_array($phase, ['player_target', 'player_move'], true);
        $ventanaActiva = $animAttackerId !== '';

        $team1 = self::marcarInteraccion(
            team: $team1,
            teamIdx: 0,
            faseSeleccion: $faseSeleccion,
            ventanaActiva: $ventanaActiva,
            actingRefId: $actingRefId,
            selectedTargetRefId: $selectedTargetRefId,
            animAttackerId: $animAttackerId,
        );
        $team2 = self::marcarInteraccion(
            team: $team2,
            teamIdx: 1,
            faseSeleccion: $faseSeleccion,
            ventanaActiva: $ventanaActiva,
            actingRefId: $actingRefId,
            selectedTargetRefId: $selectedTargetRefId,
            animAttackerId: $animAttackerId,
        );

        return [
            'team1Vista' => $team1,
            'team2Vista' => $team2,
            'turnBar' => self::turnBar($team1, $team2, $turnQueue, $phase, $actingRefId, $animAttackerId),
            'battleField' => self::battleField(
                $team1,
                $team2,
                $phase,
                $weather,
                $eventos,
                $animAttackerNombre,
                $animDefenderNombre,
                $animMoveNombre,
            ),
            'logVista' => self::logVista($log, $team1, $team2),
            'rewardsVista' => self::rewardsVista($rewards),
        ];
    }

    /**
     * Añade a cada combatiente las marcas de interacción y la clase CSS final
     * de la carta. Evita que la vista componga condicionales de estado.
     *
     * @param  list<array<string, mixed>>  $team
     * @return list<array<string, mixed>>
     */
    private static function marcarInteraccion(
        array $team,
        int $teamIdx,
        bool $faseSeleccion,
        bool $ventanaActiva,
        string $actingRefId,
        string $selectedTargetRefId,
        string $animAttackerId,
    ): array {
        foreach ($team as &$p) {
            $p['selectable'] = $faseSeleccion
                && $teamIdx === 1
                && (bool) $p['alive']
                && (bool) ($p['canTarget'] ?? true)
                && ! $ventanaActiva;
            $p['blocked'] = $faseSeleccion
                && $teamIdx === 1
                && ! (bool) ($p['canTarget'] ?? true);
            $p['acting'] = ($p['refId'] ?? '') === $actingRefId;
            $p['targeted'] = ($p['refId'] ?? '') === $selectedTargetRefId;
            $p['animAtacante'] = ($p['refId'] ?? '') === $animAttackerId;

            $clases = [
                'card',
                'w-100',
                'mb-2',
                'shadow-sm',
                'pokemon-card',
                (string) $p['claseEquipo'],
            ];

            if ($p['alive'] === false) {
                $clases[] = 'opacity-50';
            }
            if ($p['acting'] === true) {
                $clases[] = 'border border-2 border-warning';
            }
            if ($p['targeted'] === true) {
                $clases[] = 'targeted-card';
            }
            if ($p['selectable'] === true) {
                $clases[] = 'cursor-pointer border-primary';
            }
            if ($p['blocked'] === true) {
                $clases[] = 'opacity-50 cursor-not-allowed';
            }
            if ($p['hpBaja'] === true) {
                $clases[] = 'hp-bajo';
            }
            if ($p['hpCritica'] === true) {
                $clases[] = 'hp-critico';
            }
            if ($p['animAtacante'] === true) {
                $clases[] = 'anim-ataque';
            }

            $p['claseCarta'] = implode(' ', $clases);
        }
        unset($p);

        return $team;
    }

    /**
     * @param  list<array<string, mixed>>  $team1
     * @param  list<array<string, mixed>>  $team2
     * @param  list<array{team: int, index: int}>  $turnQueue
     * @return array<string, mixed>
     */
    private static function turnBar(
        array $team1,
        array $team2,
        array $turnQueue,
        string $phase,
        string $actingRefId,
        string $animAttackerId,
    ): array {
        $faseSeleccion = in_array($phase, ['player_target', 'player_move'], true);
        $sinAnimacion = $animAttackerId === '';
        $primerTurno = $turnQueue[0] ?? null;
        $segundoTurno = $turnQueue[1] ?? null;
        $puedeCambiarTurno = $primerTurno !== null
            && $segundoTurno !== null
            && $primerTurno['team'] === 0
            && $segundoTurno['team'] === 0
            && $faseSeleccion
            && $sinAnimacion;

        $siguienteTurno = null;
        if ($puedeCambiarTurno) {
            /** @var array{team: int, index: int} $segundoTurno */
            $siguienteTurno = $team1[$segundoTurno['index']] ?? null;
        }

        $actorActual = $primerTurno !== null && $primerTurno['team'] === 0
            ? ($team1[$primerTurno['index']] ?? null)
            : null;

        $items = [];
        foreach ($turnQueue as $idx => $turn) {
            $p = $turn['team'] === 0
                ? ($team1[$turn['index']] ?? null)
                : ($team2[$turn['index']] ?? null);

            if ($p === null || $p['alive'] === false) {
                continue;
            }

            $items[] = [
                'key' => $idx.'-'.$turn['team'].'-'.$turn['index'],
                'nombre' => $p['nombre'],
                'icon' => $p['icon'],
                'velocidad' => (int) round((float) $p['effectiveSpeed']),
                'badgeClass' => $idx === 0 ? 'bg-primary' : 'bg-secondary',
                'activeClass' => $idx === 0 || $p['refId'] === $actingRefId ? 'active-turn' : '',
            ];
        }

        return [
            'items' => $items,
            'mostrarEsperar' => $faseSeleccion && $sinAnimacion,
            'puedeRetrasar' => $faseSeleccion
                && $sinAnimacion
                && ($actorActual['puedeRetrasar'] ?? false) === true,
            'siguienteTurno' => $siguienteTurno,
            'puedeCambiarTurno' => $siguienteTurno !== null
                && $siguienteTurno['alive'] === true
                && $siguienteTurno['refId'] !== $actingRefId,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $team1
     * @param  list<array<string, mixed>>  $team2
     * @param  list<array<string, mixed>>  $eventos
     * @return array<string, mixed>
     */
    private static function battleField(
        array $team1,
        array $team2,
        string $phase,
        string $weather,
        array $eventos,
        string $animAttackerNombre,
        string $animDefenderNombre,
        string $animMoveNombre,
    ): array {
        $victoria = null;
        if ($phase === 'battle_over') {
            $rivalesKO = collect($team2)->every(static fn (array $p): bool => $p['alive'] === false);
            $jugadorKO = collect($team1)->every(static fn (array $p): bool => $p['alive'] === false);
            $victoria = $rivalesKO && ! $jugadorKO;
        }

        $clima = null;
        if ($weather !== '' && $weather !== 'none') {
            $tipoClima = TipoClima::tryFrom($weather);
            $clima = [
                'tipo' => $weather,
                'label' => $tipoClima?->label() ?? $weather,
                'icono' => self::iconoClima($weather),
                'variante' => in_array($weather, ['sequia', 'tormenta_arena'], true)
                    ? 'alert-warning'
                    : 'alert-info',
                'mostrarBonusRoca' => $weather === 'tormenta_arena',
            ];
        }

        return [
            'victoria' => $victoria,
            'claseFin' => $victoria === true ? 'alert-success' : 'alert-danger',
            'ocultarFin' => $eventos !== [],
            'clima' => $clima,
            'mostrarAnimacion' => $phase === 'animating'
                && $animAttackerNombre !== ''
                && $animDefenderNombre !== '',
            'animAttackerNombre' => $animAttackerNombre,
            'animDefenderNombre' => $animDefenderNombre,
            'animMoveNombre' => $animMoveNombre,
        ];
    }

    /**
     * Convierte el log plano en HTML seguro con los iconos de los combatientes.
     * Se conserva el algoritmo original (marcadores para no reemplazar HTML ya
     * inyectado y nombres ordenados de más largo a más corto).
     *
     * @param  list<string>  $log
     * @param  list<array<string, mixed>>  $team1
     * @param  list<array<string, mixed>>  $team2
     * @return list<array{html: string}>
     */
    private static function logVista(array $log, array $team1, array $team2): array
    {
        $iconsPorNombre = [];
        foreach (array_merge($team1, $team2) as $p) {
            $iconsPorNombre[(string) $p['nombre']] = (string) $p['icon'];
        }
        uksort($iconsPorNombre, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $entries = [];
        foreach (array_slice($log, -50) as $entry) {
            $entryConImagenes = e($entry);
            $iconosInsertados = [];

            foreach ($iconsPorNombre as $nombre => $icon) {
                $nombreEscapado = e($nombre);
                $marcador = '%%pokemon_'.count($iconosInsertados).'%%';

                if (str_contains($entryConImagenes, $nombreEscapado)) {
                    $entryConImagenes = str_replace($nombreEscapado, $marcador, $entryConImagenes);
                    $iconosInsertados[$marcador] = '<img src="'.e($icon).'" alt="'.$nombreEscapado.'" style="width:40px;height:40px;object-fit:contain;vertical-align:middle" class="me-1" loading="lazy">';
                }
            }

            $entries[] = [
                'html' => str_replace(
                    array_keys($iconosInsertados),
                    array_values($iconosInsertados),
                    $entryConImagenes,
                ),
            ];
        }

        return $entries;
    }

    /**
     * Normaliza las recompensas para que la vista no tenga que decidir el
     * layout ni construir rutas de imagen.
     *
     * @param  array<string, mixed>  $rewards
     * @return array<string, mixed>|null
     */
    private static function rewardsVista(array $rewards): ?array
    {
        if ($rewards === []) {
            return null;
        }

        $caramelosFamilia = self::caramelosFamilia($rewards['caramelos_familia'] ?? []);
        $caramelosEv = self::caramelosEv($rewards['caramelos_ev'] ?? []);
        $caramelosTipo = self::caramelosTipo($rewards['caramelos_tipo'] ?? []);
        $capturas = self::capturas($rewards['capturas'] ?? []);
        $tieneCaramelos = $caramelosFamilia !== [] || $caramelosEv !== [] || $caramelosTipo !== [];
        $layout = ($tieneCaramelos || $capturas !== []) ? 'ruta' : 'entrenador';

        return [
            'layout' => $layout,
            'tieneCaramelos' => $tieneCaramelos,
            'caramelos_familia' => $caramelosFamilia,
            'caramelos_ev' => $caramelosEv,
            'caramelos_tipo' => $caramelosTipo,
            'capturas' => $capturas,
            'exp_total' => (int) ($rewards['exp_total'] ?? 0),
            'exp_miembro' => (int) ($rewards['exp_miembro'] ?? 0),
            'medalla' => $rewards['medalla'] ?? null,
            'caramelos' => self::caramelosLegacy($rewards['caramelos'] ?? []),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $caramelos
     * @return list<array<string, mixed>>
     */
    private static function caramelosFamilia(array $caramelos): array
    {
        return array_map(static function (array $caramelo): array {
            $pokemonId = $caramelo['pokemon_id'] ?? null;
            $nombre = $caramelo['nombre'] ?? null;

            return array_merge($caramelo, [
                'src' => $pokemonId !== null && $pokemonId !== ''
                    ? "/images/candy_pokemon/{$pokemonId}.webp"
                    : '/images/candy_pokemon/0.webp',
                'alt' => 'Caramelo de '.($nombre ?? 'Pokémon'),
                'nombre' => $nombre,
                'cantidad' => (int) ($caramelo['cantidad'] ?? 0),
            ]);
        }, $caramelos);
    }

    /**
     * @param  list<array<string, mixed>>  $caramelos
     * @return list<array<string, mixed>>
     */
    private static function caramelosEv(array $caramelos): array
    {
        return array_map(static function (array $caramelo): array {
            $slug = $caramelo['stat_slug'] ?? null;
            $nombre = $caramelo['stat_nombre'] ?? null;
            $stat = (int) ($caramelo['stat'] ?? 0);

            return array_merge($caramelo, [
                'src' => $slug !== null && $slug !== ''
                    ? "/images/candy_ev/{$slug}.webp"
                    : '/images/candy_ev/.webp',
                'alt' => 'Caramelo EV '.($nombre ?? ''),
                'nombre' => $nombre,
                'nombre_js' => $nombre === null || $nombre === '' ? 'statName('.$stat.')' : null,
                'cantidad' => (int) ($caramelo['cantidad'] ?? 0),
            ]);
        }, $caramelos);
    }

    /**
     * @param  list<array<string, mixed>>  $caramelos
     * @return list<array<string, mixed>>
     */
    private static function caramelosTipo(array $caramelos): array
    {
        return array_map(static function (array $caramelo): array {
            $tipo = $caramelo['tipo'] ?? null;
            $slug = $caramelo['slug'] ?? '';

            return array_merge($caramelo, [
                'src' => '/images/candy_type/'.$slug.'.webp',
                'alt' => $tipo ?? 'Caramelo de tipo',
                'nombre' => $tipo,
                'cantidad' => (int) ($caramelo['cantidad'] ?? 0),
            ]);
        }, $caramelos);
    }

    /**
     * @param  list<array<string, mixed>>  $capturas
     * @return list<array<string, mixed>>
     */
    private static function capturas(array $capturas): array
    {
        return array_map(static function (array $captura): array {
            $shiny = ($captura['es_shiny'] ?? false) === true;
            $pokemonId = (int) ($captura['pokemon_id'] ?? 0);

            return array_merge($captura, [
                'pokemon_id' => $pokemonId,
                'cantidad' => (int) ($captura['cantidad'] ?? 0),
                'es_shiny' => $shiny,
                'src' => $shiny
                    ? '/images/iconos_webp/shiny/'.$pokemonId.'_s.webp'
                    : '/images/iconos_webp/'.$pokemonId.'.webp',
                'onerror' => $shiny
                    ? "this.onerror=null; this.src='/images/iconos_webp/".$pokemonId.".webp';"
                    : "this.style.display='none'",
                'title' => $shiny ? '¡Captura shiny!' : 'Pokémon capturado',
            ]);
        }, $capturas);
    }

    /**
     * @param  list<array<string, mixed>>  $caramelos
     * @return list<array<string, mixed>>
     */
    private static function caramelosLegacy(array $caramelos): array
    {
        return array_map(static fn (array $caramelo): array => [
            'imagen' => $caramelo['imagen'] ?? '/images/candy_pokemon/0.webp',
            'nombre' => $caramelo['nombre'] ?? 'Caramelo',
            'cantidad' => (int) ($caramelo['cantidad'] ?? 0),
        ], $caramelos);
    }

    private static function iconoClima(string $weather): string
    {
        return match ($weather) {
            'sequia' => '☀️',
            'diluvio' => '🌧',
            'niebla' => '🌫',
            'granizo' => '❄️',
            'tormenta_arena' => '🌪',
            'turbulencias' => '💨',
            default => '🌤',
        };
    }
}
