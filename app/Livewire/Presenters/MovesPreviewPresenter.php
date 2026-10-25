<?php

declare(strict_types=1);

namespace App\Livewire\Presenters;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\BarrerasVidaManager;
use Src\Battle\Domain\Chain\ManejadorSTAB;
use Src\Battle\Domain\Collections\CambiosStatsCollection;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;

/**
 * Presentador de presentación: arma los datos de vista de los movimientos
 * del combatiente activo contra un objetivo concreto. Produce exactamente el
 * array que consume `moves-panel.blade.php` ($currentMoves).
 *
 * No acoplado a Livewire: métodos puros, sin estado, con parámetros tipados
 * y retorno tipado. Los textos, colores, orden y marcas de "mejor movimiento"
 * se resuelven aquí; la vista sólo pinta el contrato.
 */
final class MovesPreviewPresenter
{
    /** @var array<string, string> */
    private const STAT_LABELS = [
        'attack' => 'Ataque',
        'defense' => 'Defensa',
        'spAtk' => 'At.Esp',
        'spDef' => 'Def.Esp',
        'speed' => 'Vel',
        'accuracy' => 'Precisión',
        'evasion' => 'Evasión',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function para(Combatiente $actor, AgregadoBatalla $battle, Combatiente $target): array
    {
        return self::toView($actor, $battle, $target);
    }

    /**
     * Payload completo de la vista de movimientos, ya ordenado y con los
     * campos de presentación calculados.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toView(Combatiente $actor, AgregadoBatalla $battle, Combatiente $target): array
    {
        $previews = [];

        foreach ($actor->pokemon()->moves() as $move) {
            $previews[] = self::previewDe($actor, $battle, $target, $move);
        }

        $maxDmg = 0.0;
        foreach ($previews as $preview) {
            $maxDmg = max($maxDmg, (float) $preview['daño']);
        }

        foreach ($previews as &$preview) {
            $preview['esMejor'] = $preview['potencia'] > 0
                && $maxDmg > 0
                && (float) $preview['daño'] >= $maxDmg;
        }
        unset($preview);

        // uasort (NO usort) para preservar las claves originales que coinciden
        // con la posición en moves() — selectMove usa ese índice.
        uasort($previews, static function (array $a, array $b): int {
            $danoA = (float) ($a['daño'] ?? 0);
            $danoB = (float) ($b['daño'] ?? 0);

            if ($danoA !== $danoB) {
                return $danoA < $danoB ? 1 : -1;
            }

            return strcmp(
                mb_strtolower((string) ($a['nombre'] ?? '')),
                mb_strtolower((string) ($b['nombre'] ?? '')),
            );
        });

        return $previews;
    }

    /**
     * Cabecera del panel (racha de combo del primer movimiento visible).
     *
     * @param  array<int, array<string, mixed>>  $moves
     * @return array{comboActual: int, comboMultiplicador: float}
     */
    public static function resumen(array $moves): array
    {
        $primero = $moves !== [] ? $moves[array_key_first($moves)] : [];

        return [
            'comboActual' => (int) ($primero['comboActual'] ?? 0),
            'comboMultiplicador' => (float) ($primero['comboMultiplicador'] ?? 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function previewDe(
        Combatiente $actor,
        AgregadoBatalla $battle,
        Combatiente $target,
        MovimientoBatalla $move
    ): array {
        $defenderTeam = self::defenderTeam($battle, $target);
        $categoria = $move->esEstado() ? CategoriaMovimiento::ESTADO : $move->categoria;

        $action = new AccionBatalla(
            attacker: $actor,
            defender: $target,
            move: $move,
            defenderTeamHasVanguard: $defenderTeam->tieneVanguardiaViva(),
            weather: $battle->weather(),
            isPreview: true,
            equipoDefensor: $defenderTeam,
            terreno: $battle->terreno(),
        );

        $daño = $battle->damageChain()->calculate($action);
        $directPct = min(1.0, $actor->obtenerPorcentajeDanioDirecto() + $move->perfil->danoDirectoFraccion);
        $barreraActual = $move->esEspecial()
            ? $target->defensaEspHpActual()
            : $target->defensaHpActual();
        $desglose = (new BarrerasVidaManager())->desglosarDaño(
            $daño,
            $barreraActual,
            $directPct,
            $move->perfil->multiplicadorDanoEscudo * $actor->multiplicadorDanioEscudo($action),
            $move->perfil->multiplicadorDanoSalud,
        );
        $tipoEfectivo = $actor->tipoEfectivoMovimiento($action);
        $ajusteGolpes = $actor->ajustarGolpes($action);
        $golpes = $move->perfil->golpesMax;
        $golpesMin = $move->perfil->golpesMin;
        if ($ajusteGolpes !== null) {
            $golpes = $ajusteGolpes->golpes;
            $golpesMin = $ajusteGolpes->golpes;
        }

        $esEstado = $move->esEstado();
        $efectividad = $esEstado ? 1.0 : $target->efectividadContra($tipoEfectivo);
        $estadoEfecto = $move->statusEffect;
        $motivo = $actor->motivoMovimientoNoDisponible($move);

        return [
            'nombre' => $move->nombre,
            'tipo' => $tipoEfectivo->value,
            'tipoLabel' => $tipoEfectivo->label(),
            'tipoSlug' => $tipoEfectivo->slug(),
            'tipoColor' => $tipoEfectivo->color(),
            'potencia' => $move->potencia,
            'categoria' => $categoria->value,
            'categoriaLabel' => self::categoriaLabel($categoria),
            'categoriaColor' => self::categoriaColor($categoria),
            'esEstado' => $esEstado,
            'daño' => $daño,
            'danoHp' => $desglose->danoHp,
            'danoBarrera' => $desglose->danoEscudo,
            'barreraLabel' => $move->esEspecial() ? 'DEF.ESP' : 'DEF',
            'letal' => $daño > 0 && $desglose->danoHp >= $target->hpActual(),
            'efectividad' => $efectividad,
            'efectividadTexto' => self::formatearEfectividad($efectividad),
            'efectividadColor' => self::colorEfectividad($efectividad),
            'stab' => ManejadorSTAB::tieneStabDeTipo($actor, $tipoEfectivo),
            'directo' => ! $esEstado && $directPct > 0,
            'statusEffect' => $estadoEfecto->value,
            'statusLabel' => self::estadoLabel($estadoEfecto),
            'statusEmoji' => self::estadoEmoji($estadoEfecto),
            'selfStatChanges' => self::cambiosParaVista($move->selfStatChanges),
            'targetStatChanges' => self::cambiosParaVista($move->targetStatChanges),
            'golpes' => $golpes,
            'golpesMin' => $golpesMin,
            'golpesMax' => $golpes,
            'disponible' => $actor->movimientoDisponible($move),
            'motivoNoDisponible' => $motivo,
            'motivoTexto' => self::motivoTexto($motivo, $actor->cargaMovimiento($move), $move->perfil->cargaMaxima),
            'cargaActual' => $actor->cargaMovimiento($move),
            'cargaMaxima' => $move->perfil->cargaMaxima,
            'usosRestantes' => $actor->usosRestantes($move),
            'usosMaximos' => $move->perfil->usosMaximos,
            'comboActual' => $actor->comboMovimiento() === $move->nombre ? $actor->combo() : 0,
            'comboMultiplicador' => $actor->comboMultiplicadorPara($move),
        ];
    }

    private static function categoriaLabel(CategoriaMovimiento $categoria): string
    {
        return match ($categoria) {
            CategoriaMovimiento::FISICO => 'Físico',
            CategoriaMovimiento::ESPECIAL => 'Especial',
            CategoriaMovimiento::ESTADO => 'Estado',
        };
    }

    private static function categoriaColor(CategoriaMovimiento $categoria): string
    {
        return match ($categoria) {
            CategoriaMovimiento::FISICO => 'danger',
            CategoriaMovimiento::ESPECIAL => 'primary',
            CategoriaMovimiento::ESTADO => 'secondary',
        };
    }

    private static function formatearEfectividad(float $efectividad): string
    {
        return fmod($efectividad, 1.0) === 0.0
            ? (string) (int) $efectividad
            : number_format($efectividad, 1);
    }

    private static function colorEfectividad(float $efectividad): string
    {
        if ($efectividad <= 0) {
            return 'dark';
        }

        if ($efectividad < 1) {
            return 'warning';
        }

        return $efectividad <= 1 ? 'light border' : 'success';
    }

    private static function estadoLabel(EstadoPokemon $estado): string
    {
        return match ($estado) {
            EstadoPokemon::BURN => 'QUEMAR',
            EstadoPokemon::POISON => 'ENVENENAR',
            EstadoPokemon::BAD_POISON => 'TOXICO',
            EstadoPokemon::PARALYSIS => 'PARALIZAR',
            EstadoPokemon::SLEEP => 'DORMIR',
            EstadoPokemon::FREEZE => 'CONGELAR',
            EstadoPokemon::CONFUSION => 'CONFUNDIR',
            EstadoPokemon::NONE => '',
        };
    }

    private static function estadoEmoji(EstadoPokemon $estado): string
    {
        return match ($estado) {
            EstadoPokemon::BURN => '🔥',
            EstadoPokemon::POISON, EstadoPokemon::BAD_POISON => '☠',
            EstadoPokemon::PARALYSIS => '⚡',
            EstadoPokemon::SLEEP => '💤',
            EstadoPokemon::FREEZE => '❄',
            EstadoPokemon::CONFUSION => '😵',
            EstadoPokemon::NONE => '',
        };
    }

    private static function motivoTexto(?string $motivo, int $cargaActual, int $cargaMaxima): string
    {
        return match ($motivo) {
            'cargando' => 'Cargando '.$cargaActual.'/'.$cargaMaxima,
            'sin_usos' => 'Sin usos restantes',
            'silencio' => 'Silenciado: no puede usar estado',
            'objeto_eleccion' => 'Objeto de elección: bloqueado a otro movimiento',
            default => '',
        };
    }

    /**
     * @return list<array{stat: string, factor: float, label: string, porcentaje: int, texto: string}>
     */
    private static function cambiosParaVista(CambiosStatsCollection $cambios): array
    {
        $resultado = [];

        foreach ($cambios as $cambio) {
            $stat = $cambio->statValue();
            $factor = $cambio->factorTemporal();
            $porcentaje = (int) round(($factor - 1) * 100);
            $signo = $porcentaje > 0 ? '+' : '';
            $label = self::STAT_LABELS[$stat] ?? $stat;

            $resultado[] = [
                'stat' => $stat,
                'factor' => $factor,
                'label' => $label,
                'porcentaje' => $porcentaje,
                'texto' => sprintf('%s%d%% %s', $signo, $porcentaje, $label),
            ];
        }

        return $resultado;
    }

    private static function defenderTeam(AgregadoBatalla $battle, Combatiente $defender): EquipoBatalla
    {
        return $battle->team1->findCombatant($defender) !== null
            ? $battle->team1
            : $battle->team2;
    }
}
